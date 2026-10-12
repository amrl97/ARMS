<?php

use App\Enums\AccessType;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalStep;
use App\Enums\RequestPriority;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\Department;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createInboxTestUser(string $fullname): User
{
    $department = Department::create(['name' => "{$fullname} Department"]);

    return User::factory()->create([
        'fullname' => $fullname,
        'department_id' => $department->id,
    ]);
}

test('approver inbox contains only pending approvals assigned to the signed-in user', function () {
    $approver = createInboxTestUser('Inbox approver');
    $otherApprover = createInboxTestUser('Other approver');
    $requester = createInboxTestUser('Inbox requester');
    $beneficiary = createInboxTestUser('Inbox beneficiary');
    $module = AccessModuleConfig::create([
        'module_name' => 'Inbox Module',
        'max_approvers' => 1,
        'required_approvals' => 1,
        'is_active' => true,
    ]);
    $accessRequest = AccessRequest::create([
        'requested_by' => $requester->id,
        'internal_beneficiary' => $beneficiary->id,
    ]);
    $detail = $accessRequest->details()->create([
        'module_id' => $module->id,
        'access_type' => AccessType::Privilege->value,
        'priority' => RequestPriority::High->value,
        'justification' => 'Requires elevated access for support duties.',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]);
    $assignedApproval = $detail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $approver->id,
        'status' => ApprovalStatus::Pending->value,
    ]);
    $otherModule = AccessModuleConfig::create([
        'module_name' => 'Already Approved Module',
        'max_approvers' => 1,
        'required_approvals' => 1,
        'is_active' => true,
    ]);
    $approvedDetail = $accessRequest->details()->create([
        'module_id' => $otherModule->id,
        'access_type' => AccessType::Privilege->value,
        'priority' => RequestPriority::High->value,
    ]);
    $approvedDetail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $approver->id,
        'status' => ApprovalStatus::Approved->value,
    ]);
    $detail->approvals()->create([
        'step' => ApprovalStep::Final->value,
        'approver_id' => $otherApprover->id,
        'status' => ApprovalStatus::Pending->value,
    ]);

    $this->actingAs($approver)
        ->get(route('approvals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('approvals/index')
            ->has('approvals.data', 1)
            ->where('approvals.data.0.id', $assignedApproval->id)
            ->where('approvals.data.0.step', ApprovalStep::Module->value)
            ->where('approvals.data.0.module', 'Inbox Module')
            ->where('approvals.data.0.accessType', AccessType::Privilege->value)
            ->where('approvals.data.0.priority', RequestPriority::High->value)
            ->where('approvals.data.0.justification', 'Requires elevated access for support duties.')
            ->where('approvals.data.0.startDate', '2026-11-01')
            ->where('approvals.data.0.endDate', '2026-11-30')
            ->where('approvals.data.0.requester', 'Inbox requester')
            ->where('approvals.data.0.beneficiary', 'Inbox beneficiary'));
});

test('approver inbox requires authentication', function () {
    $this->get(route('approvals.index'))
        ->assertRedirect(route('login'));
});
