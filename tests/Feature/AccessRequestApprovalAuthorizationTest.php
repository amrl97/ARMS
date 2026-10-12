<?php

use App\Enums\AccessRequestDetailStatus;
use App\Enums\AccessRequestStatus;
use App\Enums\AccessType;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalStep;
use App\Enums\RequestPriority;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\AccessRequestApproval;
use App\Models\Department;
use App\Models\ModuleApprover;
use App\Models\Role;
use App\Models\User;
use App\Policies\AccessRequestApprovalPolicy;

function createApprovalAuthorizationUser(string $name): User
{
    $department = Department::create(['name' => "{$name} Authorization Department"]);

    return User::factory()->create([
        'fullname' => $name,
        'department_id' => $department->id,
    ]);
}

function createApprovalAuthorizationRecord(
    User $requester,
    User $beneficiary,
    User $approver,
    string $step = 'module',
    string $approvalStatus = 'pending',
    string $detailStatus = 'pending',
): AccessRequestApproval {
    static $sequence = 0;
    $sequence++;

    $module = AccessModuleConfig::create([
        'module_name' => "Authorization Module {$sequence}",
        'max_approvers' => $step === 'module' ? 1 : 0,
        'required_approvals' => $step === 'module' ? 1 : 0,
        'is_active' => true,
    ]);
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
        'internal_beneficiary' => $beneficiary->id,
    ]);
    $detail = $request->details()->create([
        'module_id' => $module->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Medium->value,
        'status' => $detailStatus,
    ]);

    return $detail->approvals()->create([
        'step' => $step,
        'approver_id' => $approver->id,
        'status' => $approvalStatus,
    ]);
}

function approvalAuthorizationAllows(User $user, AccessRequestApproval $approval): bool
{
    return app(AccessRequestApprovalPolicy::class)->decide($user, $approval);
}

test('decision authorization trusts the pending row assignment rather than current module rules', function () {
    $requester = createApprovalAuthorizationUser('Authorization requester');
    $beneficiary = createApprovalAuthorizationUser('Authorization beneficiary');
    $assignedApprover = createApprovalAuthorizationUser('Assigned module approver');
    $differentApprover = createApprovalAuthorizationUser('Current module approver');
    $approval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $assignedApprover,
    );

    ModuleApprover::create([
        'module_id' => $approval->requestDetail->module_id,
        'user_id' => $differentApprover->id,
    ]);

    expect(approvalAuthorizationAllows($assignedApprover, $approval))->toBeTrue()
        ->and(approvalAuthorizationAllows($differentApprover, $approval))->toBeFalse();
});

test('decision authorization uses the assigned approver row for either stored workflow step', function () {
    $requester = createApprovalAuthorizationUser('Step authorization requester');
    $beneficiary = createApprovalAuthorizationUser('Step authorization beneficiary');
    $moduleApprover = createApprovalAuthorizationUser('Assigned module step approver');
    $finalApprover = createApprovalAuthorizationUser('Assigned final step approver');
    $moduleApproval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $moduleApprover,
        ApprovalStep::Module->value,
    );
    $finalApproval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $finalApprover,
        ApprovalStep::Final->value,
    );

    expect(approvalAuthorizationAllows($moduleApprover, $moduleApproval))->toBeTrue()
        ->and(approvalAuthorizationAllows($finalApprover, $finalApproval))->toBeTrue()
        ->and(approvalAuthorizationAllows($moduleApprover, $finalApproval))->toBeFalse();
});

test('decision authorization denies completed approval rows and non-pending details', function () {
    $requester = createApprovalAuthorizationUser('Completed-row requester');
    $beneficiary = createApprovalAuthorizationUser('Completed-row beneficiary');
    $approver = createApprovalAuthorizationUser('Completed-row approver');
    $completedApproval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $approver,
        approvalStatus: ApprovalStatus::Approved->value,
    );
    $completedDetailApproval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $approver,
        detailStatus: AccessRequestDetailStatus::Approved->value,
    );

    expect(approvalAuthorizationAllows($approver, $completedApproval))->toBeFalse()
        ->and(approvalAuthorizationAllows($approver, $completedDetailApproval))->toBeFalse();
});

test('requesters and internal beneficiaries cannot decide their own approval rows', function () {
    $requester = createApprovalAuthorizationUser('Conflict-check requester');
    $beneficiary = createApprovalAuthorizationUser('Conflict-check beneficiary');
    $otherApprover = createApprovalAuthorizationUser('Conflict-check other approver');
    $requesterApproval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $requester,
    );
    $beneficiaryApproval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $beneficiary,
    );
    $validApproval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $otherApprover,
    );

    expect(approvalAuthorizationAllows($requester, $requesterApproval))->toBeFalse()
        ->and(approvalAuthorizationAllows($beneficiary, $beneficiaryApproval))->toBeFalse()
        ->and(approvalAuthorizationAllows($otherApprover, $validApproval))->toBeTrue();
});

test('an assigned approver can approve a pending row through the workflow action', function () {
    $requester = createApprovalAuthorizationUser('Action requester');
    $beneficiary = createApprovalAuthorizationUser('Action beneficiary');
    $approver = createApprovalAuthorizationUser('Action approver');
    $admin = createApprovalAuthorizationUser('Action final Admin');
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $admin->roles()->attach($adminRole);
    $approval = createApprovalAuthorizationRecord($requester, $beneficiary, $approver);

    $this->actingAs($approver)
        ->post(route('approvals.decide', $approval), ['decision' => 'approved'])
        ->assertRedirect(route('approvals.index'))
        ->assertSessionHas('inertia.flash_data.toast.message', 'Access request approved.');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved->value)
        ->and($approval->fresh()->rejection_reason)->toBeNull()
        ->and($approval->requestDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Pending->value);
});

test('a final-step approval without module approvals approves the detail directly', function () {
    $requester = createApprovalAuthorizationUser('Direct final requester');
    $beneficiary = createApprovalAuthorizationUser('Direct final beneficiary');
    $admin = createApprovalAuthorizationUser('Direct final Admin');
    $secondAdmin = createApprovalAuthorizationUser('Second direct final Admin');
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $admin->roles()->attach($adminRole);
    $secondAdmin->roles()->attach($adminRole);
    $approval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $admin,
        step: ApprovalStep::Final->value,
    );
    $detail = $approval->requestDetail;

    expect($detail->approvals()->where('step', ApprovalStep::Module->value)->exists())->toBeFalse()
        ->and($detail->approvals()->count())->toBe(1);

    $otherFinalApproval = $detail->approvals()->create([
        'step' => ApprovalStep::Final->value,
        'approver_id' => $secondAdmin->id,
        'status' => ApprovalStatus::Pending->value,
    ]);

    $this->actingAs($admin)
        ->post(route('approvals.decide', $approval), ['decision' => 'approved'])
        ->assertRedirect(route('approvals.index'))
        ->assertSessionHas('inertia.flash_data.toast.message', 'Access request approved.');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved->value)
        ->and($approval->fresh()->step)->toBe(ApprovalStep::Final->value)
        ->and($otherFinalApproval->fresh()->status)->toBe(ApprovalStatus::Skipped->value)
        ->and($detail->fresh()->approvals()->count())->toBe(2)
        ->and($detail->fresh()->status)->toBe(AccessRequestDetailStatus::Approved->value)
        ->and($detail->request->fresh()->status)->toBe('approved');
});

test('request header is recalculated after each approval decision changes a detail status', function () {
    $requester = createApprovalAuthorizationUser('Header transition requester');
    $beneficiary = createApprovalAuthorizationUser('Header transition beneficiary');
    $admin = createApprovalAuthorizationUser('Header transition Admin');
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $admin->roles()->attach($adminRole);
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
        'internal_beneficiary' => $beneficiary->id,
    ]);
    $modules = [
        AccessModuleConfig::create([
            'module_name' => 'Header approved module',
            'max_approvers' => 0,
            'required_approvals' => 0,
            'is_active' => true,
        ]),
        AccessModuleConfig::create([
            'module_name' => 'Header rejected module',
            'max_approvers' => 0,
            'required_approvals' => 0,
            'is_active' => true,
        ]),
    ];
    $details = collect($modules)->map(function (AccessModuleConfig $module) use ($request, $admin) {
        $detail = $request->details()->create([
            'module_id' => $module->id,
            'access_type' => AccessType::NewStaff->value,
            'priority' => RequestPriority::Medium->value,
        ]);
        $approval = $detail->approvals()->create([
            'step' => ApprovalStep::Final->value,
            'approver_id' => $admin->id,
            'status' => ApprovalStatus::Pending->value,
        ]);

        return [$detail, $approval];
    });

    [$approvedDetail, $approvedRow] = $details[0];
    [$rejectedDetail, $rejectedRow] = $details[1];

    $this->actingAs($admin)
        ->post(route('approvals.decide', $approvedRow), ['decision' => 'approved'])
        ->assertRedirect(route('approvals.index'));

    expect($approvedDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Approved->value)
        ->and($request->fresh()->status)->toBe(AccessRequestStatus::InProgress->value);

    $this->actingAs($admin)
        ->post(route('approvals.decide', $rejectedRow), [
            'decision' => 'rejected',
            'rejection_reason' => 'This module is not needed.',
        ])
        ->assertRedirect(route('approvals.index'));

    expect($rejectedDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Rejected->value)
        ->and($request->fresh()->status)->toBe(AccessRequestStatus::PartiallyApproved->value);
});

test('a rejection on either workflow step requires a reason and rejects the detail', function (string $step) {
    $requester = createApprovalAuthorizationUser('Rejection requester');
    $beneficiary = createApprovalAuthorizationUser('Rejection beneficiary');
    $approver = createApprovalAuthorizationUser('Rejection approver');
    $approval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $approver,
        step: $step,
    );

    $this->actingAs($approver)
        ->post(route('approvals.decide', $approval), ['decision' => 'rejected'])
        ->assertSessionHasErrors('rejection_reason');

    $this->actingAs($approver)
        ->post(route('approvals.decide', $approval), [
            'decision' => 'rejected',
            'rejection_reason' => '   ',
        ])
        ->assertSessionHasErrors('rejection_reason');

    $this->actingAs($approver)
        ->post(route('approvals.decide', $approval), [
            'decision' => 'rejected',
            'rejection_reason' => str_repeat('x', 10001),
        ])
        ->assertSessionHasErrors('rejection_reason');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Pending->value);

    $this->actingAs($approver)
        ->post(route('approvals.decide', $approval), [
            'decision' => 'rejected',
            'rejection_reason' => 'Access is not required for the stated duties.',
        ])
        ->assertRedirect(route('approvals.index'))
        ->assertSessionHas('inertia.flash_data.toast.message', 'Access request rejected.');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Rejected->value)
        ->and($approval->fresh()->rejection_reason)->toBe('Access is not required for the stated duties.')
        ->and($approval->requestDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Rejected->value);
})->with([
    'module step' => [ApprovalStep::Module->value],
    'final step' => [ApprovalStep::Final->value],
]);

test('only the assigned approver can submit an approval decision', function () {
    $requester = createApprovalAuthorizationUser('Unauthorized action requester');
    $beneficiary = createApprovalAuthorizationUser('Unauthorized action beneficiary');
    $approver = createApprovalAuthorizationUser('Authorized action approver');
    $otherUser = createApprovalAuthorizationUser('Unauthorized action user');
    $approval = createApprovalAuthorizationRecord($requester, $beneficiary, $approver);

    $this->actingAs($otherUser)
        ->post(route('approvals.decide', $approval), ['decision' => 'approved'])
        ->assertForbidden();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Pending->value);
});

test('an approval decision cannot be submitted twice', function () {
    $requester = createApprovalAuthorizationUser('Repeated decision requester');
    $beneficiary = createApprovalAuthorizationUser('Repeated decision beneficiary');
    $approver = createApprovalAuthorizationUser('Repeated decision approver');
    $admin = createApprovalAuthorizationUser('Repeated decision final Admin');
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $admin->roles()->attach($adminRole);
    $approval = createApprovalAuthorizationRecord($requester, $beneficiary, $approver);

    $this->actingAs($approver)
        ->post(route('approvals.decide', $approval), ['decision' => 'approved'])
        ->assertRedirect(route('approvals.index'));

    $this->actingAs($approver)
        ->post(route('approvals.decide', $approval), ['decision' => 'rejected'])
        ->assertForbidden();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved->value)
        ->and($approval->fresh()->rejection_reason)->toBeNull();
});

test('requesters and internal beneficiaries cannot submit decisions for their own request lines', function () {
    $requester = createApprovalAuthorizationUser('Decision conflict requester');
    $beneficiary = createApprovalAuthorizationUser('Decision conflict beneficiary');
    $requesterApproval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $requester,
    );
    $beneficiaryApproval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $beneficiary,
    );

    $this->actingAs($requester)
        ->post(route('approvals.decide', $requesterApproval), ['decision' => 'approved'])
        ->assertForbidden();

    $this->actingAs($beneficiary)
        ->post(route('approvals.decide', $beneficiaryApproval), ['decision' => 'approved'])
        ->assertForbidden();

    expect($requesterApproval->fresh()->status)->toBe(ApprovalStatus::Pending->value)
        ->and($beneficiaryApproval->fresh()->status)->toBe(ApprovalStatus::Pending->value);
});

test('a rejection skips other pending approvals on that detail without changing its sibling request line', function () {
    $requester = createApprovalAuthorizationUser('Sibling cleanup requester');
    $beneficiary = createApprovalAuthorizationUser('Sibling cleanup beneficiary');
    $rejectingApprover = createApprovalAuthorizationUser('Rejecting approver');
    $remainingApprover = createApprovalAuthorizationUser('Remaining approver');
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
        'internal_beneficiary' => $beneficiary->id,
    ]);

    $rejectedModule = AccessModuleConfig::create([
        'module_name' => 'Rejected module step',
        'max_approvers' => 2,
        'required_approvals' => 1,
        'is_active' => true,
    ]);
    $rejectedDetail = $request->details()->create([
        'module_id' => $rejectedModule->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Medium->value,
    ]);
    $rejection = $rejectedDetail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $rejectingApprover->id,
        'status' => ApprovalStatus::Pending->value,
    ]);
    $pendingSibling = $rejectedDetail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $remainingApprover->id,
        'status' => ApprovalStatus::Pending->value,
    ]);

    $otherModule = AccessModuleConfig::create([
        'module_name' => 'Unaffected sibling module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);
    $otherDetail = $request->details()->create([
        'module_id' => $otherModule->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Low->value,
    ]);
    $unaffectedApproval = $otherDetail->approvals()->create([
        'step' => ApprovalStep::Final->value,
        'approver_id' => $remainingApprover->id,
        'status' => ApprovalStatus::Pending->value,
    ]);

    $this->actingAs($rejectingApprover)
        ->post(route('approvals.decide', $rejection), [
            'decision' => 'rejected',
            'rejection_reason' => 'The requested access is not justified.',
        ])
        ->assertRedirect(route('approvals.index'));

    expect($rejection->fresh()->status)->toBe(ApprovalStatus::Rejected->value)
        ->and($pendingSibling->fresh()->status)->toBe(ApprovalStatus::Skipped->value)
        ->and($rejectedDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Rejected->value)
        ->and($unaffectedApproval->fresh()->status)->toBe(ApprovalStatus::Pending->value)
        ->and($otherDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Pending->value)
        ->and($request->fresh()->status)->toBe('in_progress');
});

test('a module approval skips remaining module approvers and assigns active eligible Admins for final approval', function () {
    $requester = createApprovalAuthorizationUser('Module transition requester');
    $beneficiary = createApprovalAuthorizationUser('Module transition beneficiary');
    $moduleApprover = createApprovalAuthorizationUser('Module transition approver');
    $secondModuleApprover = createApprovalAuthorizationUser('Second module transition approver');
    $eligibleAdmin = createApprovalAuthorizationUser('Eligible final Admin');
    $inactiveAdmin = createApprovalAuthorizationUser('Inactive final Admin');
    $moduleApproverAdmin = createApprovalAuthorizationUser('Module approver also Admin');

    $adminRole = Role::create(['name' => 'Admin']);
    foreach ([$requester, $beneficiary, $eligibleAdmin, $inactiveAdmin, $moduleApproverAdmin] as $admin) {
        $admin->roles()->attach($adminRole);
    }
    $inactiveAdmin->forceFill(['is_active' => false])->save();

    $approval = createApprovalAuthorizationRecord(
        $requester,
        $beneficiary,
        $moduleApprover,
    );
    $detail = $approval->requestDetail;
    $detail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $secondModuleApprover->id,
        'status' => ApprovalStatus::Pending->value,
    ]);
    $detail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $moduleApproverAdmin->id,
        'status' => ApprovalStatus::Pending->value,
    ]);

    $this->actingAs($moduleApprover)
        ->post(route('approvals.decide', $approval), ['decision' => 'approved'])
        ->assertRedirect(route('approvals.index'));

    $approvals = $detail->approvals()->orderBy('id')->get();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved->value)
        ->and($approvals->where('step', ApprovalStep::Module->value)
            ->where('status', ApprovalStatus::Skipped->value))->toHaveCount(2)
        ->and($approvals->where('step', ApprovalStep::Final->value)
            ->where('status', ApprovalStatus::Pending->value)
            ->pluck('approver_id')->all())->toBe([$eligibleAdmin->id])
        ->and($detail->fresh()->status)->toBe(AccessRequestDetailStatus::Pending->value);

    $finalApproval = $approvals->firstWhere('step', ApprovalStep::Final->value);

    $this->actingAs($eligibleAdmin)
        ->post(route('approvals.decide', $finalApproval), ['decision' => 'approved'])
        ->assertRedirect(route('approvals.index'));

    expect($finalApproval->fresh()->status)->toBe(ApprovalStatus::Approved->value)
        ->and($detail->fresh()->status)->toBe(AccessRequestDetailStatus::Approved->value);
});

test('module approval rolls back when no eligible active Admin can receive final approval', function () {
    $requester = createApprovalAuthorizationUser('No Admin requester');
    $beneficiary = createApprovalAuthorizationUser('No Admin beneficiary');
    $moduleApprover = createApprovalAuthorizationUser('No Admin module approver');
    $approval = createApprovalAuthorizationRecord($requester, $beneficiary, $moduleApprover);
    $detail = $approval->requestDetail;
    $otherModuleApproval = $detail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => createApprovalAuthorizationUser('No Admin second approver')->id,
        'status' => ApprovalStatus::Pending->value,
    ]);

    $this->actingAs($moduleApprover)
        ->post(route('approvals.decide', $approval), ['decision' => 'approved'])
        ->assertSessionHasErrors('decision');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Pending->value)
        ->and($otherModuleApproval->fresh()->status)->toBe(ApprovalStatus::Pending->value)
        ->and($detail->fresh()->approvals()->where('step', ApprovalStep::Final->value)->count())->toBe(0);
});
