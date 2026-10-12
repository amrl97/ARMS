<?php

use App\Enums\AccessRequestDetailStatus;
use App\Enums\AccessType;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalStep;
use App\Enums\RequestPriority;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\AccessRequestDetail;
use App\Models\Department;
use App\Models\ModuleApprover;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ApprovalCoverageUnavailable;
use App\Services\RoleAssignmentService;
use Illuminate\Support\Facades\Notification;

function createDeactivatedApproverDetail(
    User $requester,
    User $beneficiary,
    AccessModuleConfig $module,
    AccessRequestDetailStatus $status = AccessRequestDetailStatus::Pending,
): AccessRequestDetail {
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
        'internal_beneficiary' => $beneficiary->id,
    ]);

    return $request->details()->create([
        'module_id' => $module->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Medium->value,
        'status' => $status->value,
    ]);
}

function deactivateApprover(User $user): void
{
    app(RoleAssignmentService::class)->updateUser(
        $user,
        ['is_active' => false],
        $user->roles()->pluck('roles.id')->all(),
    );
}

test('module-step deactivation skips the inactive row and adds eligible users from current module rules', function () {
    Notification::fake();

    $department = Department::create(['name' => 'Module Replacement Department']);
    $staffRole = Role::create(['name' => 'Module Replacement Staff']);
    $requester = User::factory()->create(['department_id' => $department->id]);
    $beneficiary = User::factory()->create(['department_id' => $department->id]);
    $inactiveApprover = User::factory()->create(['department_id' => $department->id]);
    $remainingApprover = User::factory()->create(['department_id' => $department->id]);
    $replacement = User::factory()->create(['department_id' => $department->id]);
    $alreadyAssigned = User::factory()->create(['department_id' => $department->id]);
    $inactiveApprover->roles()->attach($staffRole);
    $remainingApprover->roles()->attach($staffRole);
    $replacement->roles()->attach($staffRole);
    $alreadyAssigned->roles()->attach($staffRole);

    $module = AccessModuleConfig::create([
        'module_name' => 'Module Replacement',
        'max_approvers' => 4,
        'required_approvals' => 1,
        'is_active' => true,
    ]);
    ModuleApprover::create([
        'module_id' => $module->id,
        'user_id' => $replacement->id,
    ]);
    ModuleApprover::create([
        'module_id' => $module->id,
        'user_id' => $alreadyAssigned->id,
    ]);
    ModuleApprover::create([
        'module_id' => $module->id,
        'user_id' => $requester->id,
    ]);
    ModuleApprover::create([
        'module_id' => $module->id,
        'user_id' => $beneficiary->id,
    ]);

    $detail = createDeactivatedApproverDetail(
        $requester,
        $beneficiary,
        $module,
    );
    $inactiveRow = $detail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $inactiveApprover->id,
        'status' => ApprovalStatus::Pending->value,
    ]);
    $remainingRow = $detail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $remainingApprover->id,
        'status' => ApprovalStatus::Pending->value,
    ]);
    $existingRow = $detail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $alreadyAssigned->id,
        'status' => ApprovalStatus::Skipped->value,
    ]);
    $decidedDetail = createDeactivatedApproverDetail(
        $requester,
        $beneficiary,
        $module,
        AccessRequestDetailStatus::Approved,
    );
    $priorDecision = $decidedDetail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $inactiveApprover->id,
        'status' => ApprovalStatus::Approved->value,
    ]);

    deactivateApprover($inactiveApprover);

    expect($inactiveRow->fresh()->status)->toBe(ApprovalStatus::Skipped->value)
        ->and($remainingRow->fresh()->status)->toBe(ApprovalStatus::Pending->value)
        ->and($existingRow->fresh()->status)->toBe(ApprovalStatus::Skipped->value)
        ->and($detail->fresh()->approvals()->where('approver_id', $replacement->id)->value('status'))
        ->toBe(ApprovalStatus::Pending->value)
        ->and($detail->fresh()->approvals()->where('approver_id', $alreadyAssigned->id)->count())
        ->toBe(1)
        ->and($detail->fresh()->approvals()->whereIn('approver_id', [
            $requester->id,
            $beneficiary->id,
        ])->exists())
        ->toBeFalse()
        ->and($priorDecision->fresh()->status)->toBe(ApprovalStatus::Approved->value)
        ->and($decidedDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Approved->value);

    Notification::assertNothingSent();
});

test('final-step deactivation assigns active Admin replacements without changing other assignments', function () {
    $department = Department::create(['name' => 'Final Replacement Department']);
    $adminRole = Role::create(['name' => 'Admin']);
    $requester = User::factory()->create(['department_id' => $department->id]);
    $beneficiary = User::factory()->create(['department_id' => $department->id]);
    $inactiveAdmin = User::factory()->create(['department_id' => $department->id]);
    $remainingAdmin = User::factory()->create(['department_id' => $department->id]);
    $replacementAdmin = User::factory()->create(['department_id' => $department->id]);
    $inactiveAdmin->roles()->attach($adminRole);
    $remainingAdmin->roles()->attach($adminRole);
    $replacementAdmin->roles()->attach($adminRole);
    $module = AccessModuleConfig::create([
        'module_name' => 'Final Replacement Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);

    $detail = createDeactivatedApproverDetail(
        $requester,
        $beneficiary,
        $module,
    );
    $inactiveRow = $detail->approvals()->create([
        'step' => ApprovalStep::Final->value,
        'approver_id' => $inactiveAdmin->id,
        'status' => ApprovalStatus::Pending->value,
    ]);
    $remainingRow = $detail->approvals()->create([
        'step' => ApprovalStep::Final->value,
        'approver_id' => $remainingAdmin->id,
        'status' => ApprovalStatus::Pending->value,
    ]);

    deactivateApprover($inactiveAdmin);

    expect($inactiveRow->fresh()->status)->toBe(ApprovalStatus::Skipped->value)
        ->and($remainingRow->fresh()->status)->toBe(ApprovalStatus::Pending->value)
        ->and($detail->fresh()->approvals()->where('approver_id', $replacementAdmin->id)->value('status'))
        ->toBe(ApprovalStatus::Pending->value)
        ->and($detail->fresh()->status)->toBe(AccessRequestDetailStatus::Pending->value);
});

test('Super Admins are notified when deactivation leaves a pending step below its approval threshold', function () {
    Notification::fake();

    $department = Department::create(['name' => 'Uncovered Approval Department']);
    $superAdminRole = Role::create(['name' => 'Super Admin']);
    $adminRole = Role::create(['name' => 'Admin']);
    $superAdmin = User::factory()->create(['department_id' => $department->id]);
    $superAdmin->roles()->attach($superAdminRole);
    $requester = User::factory()->create(['department_id' => $department->id]);
    $beneficiary = User::factory()->create(['department_id' => $department->id]);
    $onlyAdmin = User::factory()->create(['department_id' => $department->id]);
    $onlyAdmin->roles()->attach($adminRole);
    $module = AccessModuleConfig::create([
        'module_name' => 'Uncovered Final Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);

    $detail = createDeactivatedApproverDetail($requester, $beneficiary, $module);
    $deactivatedRow = $detail->approvals()->create([
        'step' => ApprovalStep::Final->value,
        'approver_id' => $onlyAdmin->id,
        'status' => ApprovalStatus::Pending->value,
    ]);

    deactivateApprover($onlyAdmin);

    expect($deactivatedRow->fresh()->status)->toBe(ApprovalStatus::Skipped->value)
        ->and($detail->fresh()->status)->toBe(AccessRequestDetailStatus::Pending->value)
        ->and($detail->fresh()->approvals()->where('status', ApprovalStatus::Pending->value)->count())
        ->toBe(0);

    Notification::assertSentTo(
        $superAdmin,
        ApprovalCoverageUnavailable::class,
        function (ApprovalCoverageUnavailable $notification) use ($detail): bool {
            $mail = $notification->toMail(new stdClass);

            return collect($mail->introLines)->contains(
                fn (string $line): bool => str_contains($line, "line #{$detail->id}")
                    && str_contains($line, 'final step')
                    && str_contains($line, '0 of 1 required approver(s) available'),
            );
        },
    );
});
