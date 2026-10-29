<?php

use App\Enums\AccessRequestDetailStatus;
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
    $module->update(['is_active' => false]);
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
            ->where('auth.canApprove', true)
            ->has('moduleApprovals.data', 1)
            ->where('moduleApprovals.data.0.id', $assignedApproval->id)
            ->where('moduleApprovals.data.0.requestId', $accessRequest->id)
            ->where('moduleApprovals.data.0.step', ApprovalStep::Module->value)
            ->where('moduleApprovals.data.0.module', 'Inbox Module')
            ->where('moduleApprovals.data.0.accessType', AccessType::Privilege->value)
            ->where('moduleApprovals.data.0.priority', RequestPriority::High->value)
            ->where('moduleApprovals.data.0.justification', 'Requires elevated access for support duties.')
            ->where('moduleApprovals.data.0.startDate', '2026-11-01')
            ->where('moduleApprovals.data.0.endDate', '2026-11-30')
            ->where('moduleApprovals.data.0.requester', 'Inbox requester')
            ->where('moduleApprovals.data.0.beneficiary', 'Inbox beneficiary')
            ->has('finalApprovals.data', 0));
});

test('final inbox uses assigned pending rows even when module configuration is inactive', function () {
    $finalAdmin = createInboxTestUser('Final Inbox Admin');
    $requester = createInboxTestUser('Final Inbox Requester');
    $module = AccessModuleConfig::create([
        'module_name' => 'Inactive Historical Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => false,
    ]);
    $accessRequest = AccessRequest::create(['requested_by' => $requester->id]);
    $pendingDetail = $accessRequest->details()->create([
        'module_id' => $module->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Medium->value,
    ]);
    $finalApproval = $pendingDetail->approvals()->create([
        'step' => ApprovalStep::Final->value,
        'approver_id' => $finalAdmin->id,
        'status' => ApprovalStatus::Pending->value,
    ]);

    $rejectedModule = AccessModuleConfig::create([
        'module_name' => 'Rejected Inbox Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => false,
    ]);
    $rejectedDetail = $accessRequest->details()->create([
        'module_id' => $rejectedModule->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Low->value,
        'status' => AccessRequestDetailStatus::Rejected->value,
    ]);
    $rejectedDetail->approvals()->create([
        'step' => ApprovalStep::Final->value,
        'approver_id' => $finalAdmin->id,
        'status' => ApprovalStatus::Pending->value,
    ]);

    $this->actingAs($finalAdmin)
        ->get(route('approvals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('approvals/index')
            ->has('moduleApprovals.data', 0)
            ->has('finalApprovals.data', 1)
            ->where('finalApprovals.data.0.id', $finalApproval->id)
            ->where('finalApprovals.data.0.requestId', $accessRequest->id)
            ->where('finalApprovals.data.0.step', ApprovalStep::Final->value)
            ->where('finalApprovals.data.0.module', 'Inactive Historical Module'));
});

test('approver inbox requires authentication', function () {
    $this->get(route('approvals.index'))
        ->assertRedirect(route('login'));
});

test('assigned approvers can view an approval in its own view', function () {
    $approver = createInboxTestUser('Approval detail approver');
    $requester = createInboxTestUser('Approval detail requester');
    $beneficiary = createInboxTestUser('Approval detail beneficiary');
    $module = AccessModuleConfig::create([
        'module_name' => 'Approval Detail Module',
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
        'justification' => 'Approval-specific detail view.',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]);
    $approval = $detail->approvals()->create([
        'step' => ApprovalStep::Module->value,
        'approver_id' => $approver->id,
        'status' => ApprovalStatus::Pending->value,
    ]);

    $this->actingAs($approver)
        ->get(route('approvals.show', $approval))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('approvals/show')
            ->where('approval.id', $approval->id)
            ->where('approval.step', ApprovalStep::Module->value)
            ->where('approval.status', ApprovalStatus::Pending->value)
            ->where('approval.requestId', $accessRequest->id)
            ->where('approval.module', 'Approval Detail Module')
            ->where('approval.accessType', AccessType::Privilege->value)
            ->where('approval.priority', RequestPriority::High->value)
            ->where('approval.justification', 'Approval-specific detail view.')
            ->where('approval.requester', 'Approval detail requester')
            ->where('approval.beneficiary', 'Approval detail beneficiary')
            ->where('approval.history.0.approver', 'Approval detail approver'));

    $this->actingAs($requester)
        ->get(route('approvals.show', $approval))
        ->assertForbidden();
});
