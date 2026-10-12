<?php

use App\Enums\AccessRequestDetailStatus;
use App\Enums\AccessRequestStatus;
use App\Enums\AccessType;
use App\Enums\RequestPriority;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\AccessRequestAttachment;
use App\Models\AccessRequestDetail;
use App\Models\Department;
use App\Models\ModuleApprover;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function submissionTestUser(string $name): User
{
    static $sequence = 0;
    $sequence++;
    $department = Department::create(['name' => "Submission Test Department {$sequence}"]);

    return User::factory()->create([
        'fullname' => $name,
        'department_id' => $department->id,
    ]);
}

function submissionTestAdmin(string $name = 'Final approver', bool $isActive = true): User
{
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $admin = submissionTestUser($name);
    $admin->forceFill(['is_active' => $isActive])->save();
    $admin->roles()->attach($adminRole);

    return $admin;
}

function submissionTestLine(AccessModuleConfig $module): array
{
    return [
        'module_id' => $module->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Medium->value,
        'justification' => 'Business need',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ];
}

function submissionTestPayload(array $moduleLines, array $overrides = []): array
{
    return array_replace([
        'beneficiary_type' => 'self',
        'internal_beneficiary' => null,
        'external_beneficiary' => null,
        'module_lines' => $moduleLines,
    ], $overrides);
}

test('submitting a request stores its header, details, module approvals, and private attachments together', function () {
    Storage::fake('local');

    $requester = submissionTestUser('Submission requester');
    $beneficiary = submissionTestUser('Request beneficiary');
    $approver = submissionTestUser('Module approver');
    $finalAdmin = submissionTestAdmin();
    $secondFinalAdmin = submissionTestAdmin('Second final approver');
    $spoofedRequester = submissionTestUser('Spoofed requester');
    $approvedModule = AccessModuleConfig::create([
        'module_name' => 'Module Approval Required',
        'max_approvers' => 1,
        'required_approvals' => 1,
        'is_active' => true,
    ]);
    ModuleApprover::create([
        'module_id' => $approvedModule->id,
        'user_id' => $approver->id,
    ]);
    $finalOnlyModule = AccessModuleConfig::create([
        'module_name' => 'Final Approval Only',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs($requester)->post(route('requests.store'), submissionTestPayload([
        submissionTestLine($approvedModule),
        submissionTestLine($finalOnlyModule),
    ], [
        'beneficiary_type' => 'internal',
        'internal_beneficiary' => $beneficiary->id,
        'requested_by' => $spoofedRequester->id,
        'attachments' => [
            UploadedFile::fake()->create('approval.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->create('diagram.png', 30, 'image/png'),
        ],
    ]));

    $response->assertRedirect(route('requests.create'));
    $response->assertSessionHas('inertia.flash_data.toast.message', 'Access request submitted successfully.');

    $request = AccessRequest::with(['details.approvals', 'attachments'])->firstOrFail();

    expect($request->requested_by)->toBe($requester->id)
        ->and($request->internal_beneficiary)->toBe($beneficiary->id)
        ->and($request->external_beneficiary)->toBeNull()
        ->and($request->details)->toHaveCount(2)
        ->and($request->details[0]->approvals)->toHaveCount(1)
        ->and($request->details[0]->approvals[0]->step)->toBe('module')
        ->and($request->details[0]->approvals[0]->approver_id)->toBe($approver->id)
        ->and($request->details[1]->approvals)->toHaveCount(2)
        ->and($request->details[1]->approvals->every(
            fn ($approval): bool => $approval->step === 'final' && $approval->status === 'pending',
        ))->toBeTrue()
        ->and($request->details[1]->approvals->pluck('approver_id')->all())->toBe([
            $finalAdmin->id,
            $secondFinalAdmin->id,
        ])
        ->and($request->attachments)->toHaveCount(2);

    foreach ($request->attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->file_path);
        expect($attachment->file_size)->toBeGreaterThan(0);
    }

    $this->assertDatabaseCount('access_requests', 1);
    $this->assertDatabaseCount('access_request_details', 2);
    $this->assertDatabaseCount('access_request_approvals', 3);
    $this->assertDatabaseHas('access_request_approvals', ['step' => 'final', 'status' => 'pending']);
    $this->assertDatabaseCount('access_request_attachments', 2);
});

test('module lines progress independently and only affect their own detail status', function () {
    $requester = submissionTestUser('Independent line requester');
    $moduleApprover = submissionTestUser('Independent module approver');
    submissionTestAdmin();

    $moduleReviewModule = AccessModuleConfig::create([
        'module_name' => 'Independent Module Review',
        'max_approvers' => 1,
        'required_approvals' => 1,
        'is_active' => true,
    ]);
    ModuleApprover::create([
        'module_id' => $moduleReviewModule->id,
        'user_id' => $moduleApprover->id,
    ]);
    $finalOnlyModule = AccessModuleConfig::create([
        'module_name' => 'Independent Final Only',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($requester)
        ->post(route('requests.store'), submissionTestPayload([
            submissionTestLine($moduleReviewModule),
            submissionTestLine($finalOnlyModule),
        ]))
        ->assertRedirect(route('requests.create'));

    $request = AccessRequest::with('details.approvals')->firstOrFail();
    $moduleReviewDetail = $request->details->firstWhere('module_id', $moduleReviewModule->id);
    $finalOnlyDetail = $request->details->firstWhere('module_id', $finalOnlyModule->id);

    expect($moduleReviewDetail)->toBeInstanceOf(AccessRequestDetail::class)
        ->and($finalOnlyDetail)->toBeInstanceOf(AccessRequestDetail::class)
        ->and($moduleReviewDetail->status)->toBe(AccessRequestDetailStatus::Pending->value)
        ->and($finalOnlyDetail->status)->toBe(AccessRequestDetailStatus::Pending->value)
        ->and($moduleReviewDetail->approvals->every(
            fn ($approval): bool => $approval->step === 'module',
        ))->toBeTrue()
        ->and($finalOnlyDetail->approvals->every(
            fn ($approval): bool => $approval->step === 'final',
        ))->toBeTrue();

    $moduleReviewDetail->status = AccessRequestDetailStatus::Rejected->value;
    $moduleReviewDetail->save();

    expect($moduleReviewDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Rejected->value)
        ->and($finalOnlyDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Pending->value)
        ->and($request->fresh()->status)->toBe(AccessRequestStatus::InProgress->value);

    $finalOnlyDetail->status = AccessRequestDetailStatus::Approved->value;
    $finalOnlyDetail->save();

    expect($moduleReviewDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Rejected->value)
        ->and($finalOnlyDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Approved->value)
        ->and($request->fresh()->status)->toBe(AccessRequestStatus::PartiallyApproved->value);
});

test('submitting a self-request stores both beneficiary fields as null', function () {
    $requester = submissionTestUser('Self requester');
    submissionTestAdmin();
    $module = AccessModuleConfig::create([
        'module_name' => 'Self Request Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($requester)
        ->post(route('requests.store'), submissionTestPayload([submissionTestLine($module)]))
        ->assertRedirect(route('requests.create'));

    $request = AccessRequest::firstOrFail();

    expect($request->requested_by)->toBe($requester->id)
        ->and($request->internal_beneficiary)->toBeNull()
        ->and($request->external_beneficiary)->toBeNull()
        ->and($request->resolveBeneficiary()->is($requester))->toBeTrue();
});

test('submitting for an external beneficiary stores only the external beneficiary name', function () {
    $requester = submissionTestUser('External requester');
    submissionTestAdmin();
    $module = AccessModuleConfig::create([
        'module_name' => 'External Request Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($requester)
        ->post(route('requests.store'), submissionTestPayload([submissionTestLine($module)], [
            'beneficiary_type' => 'external',
            'external_beneficiary' => '  Example Vendor  ',
        ]))
        ->assertRedirect(route('requests.create'));

    $request = AccessRequest::firstOrFail();

    expect($request->requested_by)->toBe($requester->id)
        ->and($request->internal_beneficiary)->toBeNull()
        ->and($request->external_beneficiary)->toBe('Example Vendor')
        ->and($request->resolveBeneficiary())->toBe('Example Vendor');
});

test('module approval is skipped when requester and beneficiary are the only eligible approvers', function () {
    $requester = submissionTestUser('Conflicted module requester');
    $finalAdmin = submissionTestAdmin('Conflicted module final Admin');
    $module = AccessModuleConfig::create([
        'module_name' => 'Conflict Exclusion Module',
        'max_approvers' => 1,
        'required_approvals' => 1,
        'is_active' => true,
    ]);
    ModuleApprover::create([
        'module_id' => $module->id,
        'user_id' => $requester->id,
    ]);

    $this->actingAs($requester)
        ->post(route('requests.store'), submissionTestPayload([submissionTestLine($module)]))
        ->assertRedirect(route('requests.create'));

    $detail = AccessRequestDetail::with('approvals')->firstOrFail();
    expect($detail->approvals)->toHaveCount(1)
        ->and($detail->approvals[0]->step)->toBe('final')
        ->and($detail->approvals[0]->approver_id)->toBe($finalAdmin->id);
});

test('unresolved module approval configuration rolls back the entire request', function () {
    Storage::fake('local');

    $requester = submissionTestUser('Unresolved requester');
    submissionTestAdmin();
    $unassignedRole = Role::create(['name' => 'Unassigned submission approver']);
    $module = AccessModuleConfig::create([
        'module_name' => 'Unresolved Approval Module',
        'max_approvers' => 1,
        'required_approvals' => 1,
        'is_active' => true,
    ]);
    ModuleApprover::create([
        'module_id' => $module->id,
        'role_id' => $unassignedRole->id,
    ]);

    $this->actingAs($requester)
        ->postJson(route('requests.store'), submissionTestPayload([submissionTestLine($module)], [
            'attachments' => [UploadedFile::fake()->create('support.pdf', 10, 'application/pdf')],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('module_lines.0.module_id')
        ->assertSeeText('No active eligible approver is configured for this module. Contact an administrator to review its approver rules.');

    $this->assertDatabaseCount('access_requests', 0);
    $this->assertDatabaseCount('access_request_details', 0);
    $this->assertDatabaseCount('access_request_approvals', 0);
    $this->assertDatabaseCount('access_request_attachments', 0);
    Storage::disk('local')->assertDirectoryEmpty('access-requests');
});

test('an attachment database failure rolls back request data and removes the stored file', function () {
    Storage::fake('local');

    $requester = submissionTestUser('Attachment rollback requester');
    submissionTestAdmin();
    $module = AccessModuleConfig::create([
        'module_name' => 'Attachment Rollback Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);
    AccessRequestAttachment::creating(function (AccessRequestAttachment $attachment): void {
        if ($attachment->file_name === 'rollback.pdf') {
            throw new RuntimeException('Simulated attachment metadata write failure.');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($requester)->post(route('requests.store'), submissionTestPayload([
        submissionTestLine($module),
    ], [
        'attachments' => [UploadedFile::fake()->create('rollback.pdf', 10, 'application/pdf')],
    ])))->toThrow(RuntimeException::class, 'Simulated attachment metadata write failure.');

    $this->assertDatabaseCount('access_requests', 0);
    $this->assertDatabaseCount('access_request_details', 0);
    $this->assertDatabaseCount('access_request_approvals', 0);
    $this->assertDatabaseCount('access_request_attachments', 0);
    Storage::disk('local')->assertDirectoryEmpty('access-requests');
});

test('submission is blocked if there is no active Admin for final approval', function () {
    $requester = submissionTestUser('No final approver requester');
    submissionTestAdmin('Inactive final approver', isActive: false);
    $module = AccessModuleConfig::create([
        'module_name' => 'Missing Final Approver Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($requester)
        ->postJson(route('requests.store'), submissionTestPayload([submissionTestLine($module)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('module_lines')
        ->assertSeeText('No active Admin is available for final approval. Contact an administrator before submitting this request.');

    $this->assertDatabaseCount('access_requests', 0);
    $this->assertDatabaseCount('access_request_details', 0);
    $this->assertDatabaseCount('access_request_approvals', 0);
});

test('request attachment validation enforces the count, file types, and size limit', function () {
    $requester = submissionTestUser('Attachment limits requester');
    $module = AccessModuleConfig::create([
        'module_name' => 'Attachment Limits Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);
    $line = submissionTestLine($module);

    $this->actingAs($requester)
        ->postJson(route('requests.store'), submissionTestPayload([$line], [
            'attachments' => array_map(
                static fn (int $index): UploadedFile => UploadedFile::fake()->create("doc-{$index}.pdf", 1, 'application/pdf'),
                range(1, 6),
            ),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachments');

    $this->postJson(route('requests.store'), submissionTestPayload([$line], [
        'attachments' => [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachments.0');

    $this->postJson(route('requests.store'), submissionTestPayload([$line], [
        'attachments' => [UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachments.0');

    expect(AccessRequest::query()->count())->toBe(0);
});
