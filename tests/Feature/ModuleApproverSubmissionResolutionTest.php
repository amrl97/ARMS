<?php

use App\Models\AccessModuleConfig;
use App\Models\Department;
use App\Models\ModuleApprover;
use App\Models\Role;
use App\Models\User;
use App\Services\ModuleApproverResolver;

function submissionResolutionModule(int $maxApprovers = 2): AccessModuleConfig
{
    return AccessModuleConfig::create([
        'module_name' => 'Submission Resolution '.$maxApprovers,
        'max_approvers' => $maxApprovers,
        'required_approvals' => $maxApprovers === 0 ? 0 : 1,
        'is_active' => true,
    ]);
}

test('submission resolution excludes requester but assigns remaining module approvers', function () {
    $department = Department::create(['name' => 'Submission Requester']);
    $role = Role::create(['name' => 'Submission Approver']);
    $requester = User::factory()->create(['department_id' => $department->id]);
    $otherApprover = User::factory()->create(['department_id' => $department->id]);
    $requester->roles()->attach($role);
    $otherApprover->roles()->attach($role);

    $module = submissionResolutionModule();
    ModuleApprover::create(['module_id' => $module->id, 'role_id' => $role->id]);

    $result = app(ModuleApproverResolver::class)->resolveForSubmission($module, $requester, $requester);

    expect($result->approvers->modelKeys())->toBe([$otherApprover->id])
        ->and($result->hasEligibleApproversBeforeExclusions)->toBeTrue()
        ->and($result->skipModuleStep)->toBeFalse()
        ->and($result->requiresConfiguration())->toBeFalse();
});

test('submission resolution excludes both requester and internal beneficiary', function () {
    $department = Department::create(['name' => 'Submission Beneficiary']);
    $role = Role::create(['name' => 'Submission Approver']);
    $requester = User::factory()->create(['department_id' => $department->id]);
    $beneficiary = User::factory()->create(['department_id' => $department->id]);
    $otherApprover = User::factory()->create(['department_id' => $department->id]);
    foreach ([$requester, $beneficiary, $otherApprover] as $user) {
        $user->roles()->attach($role);
    }

    $module = submissionResolutionModule();
    ModuleApprover::create(['module_id' => $module->id, 'role_id' => $role->id]);

    $result = app(ModuleApproverResolver::class)->resolveForSubmission($module, $requester, $beneficiary);

    expect($result->approvers->modelKeys())->toBe([$otherApprover->id])
        ->and($result->skipModuleStep)->toBeFalse();
});

test('submission resolution skips module approval when every eligible approver is conflicted', function () {
    $department = Department::create(['name' => 'Self Request Department']);
    $role = Role::create(['name' => 'Self Request HOD']);
    $requester = User::factory()->create(['department_id' => $department->id]);
    $requester->roles()->attach($role);

    $module = submissionResolutionModule();
    ModuleApprover::create([
        'module_id' => $module->id,
        'role_id' => $role->id,
        'department_id' => $department->id,
    ]);

    $result = app(ModuleApproverResolver::class)->resolveForSubmission($module, $requester, $requester);

    expect($result->approvers)->toBeEmpty()
        ->and($result->hasEligibleApproversBeforeExclusions)->toBeTrue()
        ->and($result->skipModuleStep)->toBeTrue()
        ->and($result->requiresConfiguration())->toBeFalse();
});

test('submission resolution signals configuration error when no eligible rule match exists', function () {
    $department = Department::create(['name' => 'Unresolved Department']);
    $requester = User::factory()->create(['department_id' => $department->id]);
    $role = Role::create(['name' => 'Unassigned Request Approver']);
    $module = submissionResolutionModule();
    ModuleApprover::create(['module_id' => $module->id, 'role_id' => $role->id]);

    $result = app(ModuleApproverResolver::class)->resolveForSubmission($module, $requester, $requester);

    expect($result->approvers)->toBeEmpty()
        ->and($result->hasEligibleApproversBeforeExclusions)->toBeFalse()
        ->and($result->skipModuleStep)->toBeFalse()
        ->and($result->requiresConfiguration())->toBeTrue();
});

test('submission resolution skips module step when the module has no module approvers configured', function () {
    $department = Department::create(['name' => 'Final Only Department']);
    $requester = User::factory()->create(['department_id' => $department->id]);
    $module = submissionResolutionModule(maxApprovers: 0);

    $result = app(ModuleApproverResolver::class)->resolveForSubmission($module, $requester, $requester);

    expect($result->skipModuleStep)->toBeTrue()
        ->and($result->requiresConfiguration())->toBeFalse();
});
