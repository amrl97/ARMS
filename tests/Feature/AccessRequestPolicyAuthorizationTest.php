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
use App\Policies\AccessRequestPolicy;
use Inertia\Testing\AssertableInertia as Assert;

function createRequestPolicyUser(string $name): User
{
    static $sequence = 0;
    $sequence++;
    $department = Department::create(['name' => "{$name} Request Policy Department {$sequence}"]);

    return User::factory()->create([
        'fullname' => $name,
        'department_id' => $department->id,
    ]);
}

function createRequestPolicyRequest(
    User $requester,
    ?User $beneficiary = null,
    ?User $approver = null,
    string $detailStatus = 'pending',
): AccessRequest {
    static $sequence = 0;
    $sequence++;
    $module = AccessModuleConfig::create([
        'module_name' => "Request Policy Module {$sequence}",
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
        'internal_beneficiary' => $beneficiary?->id,
    ]);
    $detail = $request->details()->create([
        'module_id' => $module->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Medium->value,
        'status' => $detailStatus,
    ]);

    if ($approver !== null) {
        $detail->approvals()->create([
            'step' => ApprovalStep::Final->value,
            'approver_id' => $approver->id,
            'status' => ApprovalStatus::Pending->value,
        ]);
    }

    return $request;
}

test('request visibility is limited to its requester, internal beneficiary, and assigned approvers', function () {
    $requester = createRequestPolicyUser('Request owner');
    $beneficiary = createRequestPolicyUser('Request beneficiary');
    $assignedApprover = createRequestPolicyUser('Assigned request approver');
    $unrelatedUser = createRequestPolicyUser('Unrelated request user');
    $request = createRequestPolicyRequest($requester, $beneficiary, $assignedApprover);
    $policy = app(AccessRequestPolicy::class);

    expect($policy->view($requester, $request))->toBeTrue()
        ->and($policy->view($beneficiary, $request))->toBeTrue()
        ->and($policy->view($assignedApprover, $request))->toBeTrue()
        ->and($policy->view($unrelatedUser, $request))->toBeFalse();
});

test('request listing returns only requests visible to the authenticated user', function () {
    $requester = createRequestPolicyUser('Visible request owner');
    $assignedApprover = createRequestPolicyUser('Visible request approver');
    $unrelatedUser = createRequestPolicyUser('Hidden request user');
    $visibleRequest = createRequestPolicyRequest($requester, approver: $assignedApprover);
    createRequestPolicyRequest($unrelatedUser);

    $this->actingAs($requester)
        ->get(route('requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('requests/index')
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $visibleRequest->id));
});

test('only the requester may cancel a request while it has pending lines', function () {
    $requester = createRequestPolicyUser('Cancellation requester');
    $otherUser = createRequestPolicyUser('Cancellation other user');
    $pendingRequest = createRequestPolicyRequest($requester);
    $completedRequest = createRequestPolicyRequest(
        $requester,
        detailStatus: AccessRequestDetailStatus::Approved->value,
    );
    $policy = app(AccessRequestPolicy::class);

    expect($policy->cancel($requester, $pendingRequest))->toBeTrue()
        ->and($policy->cancel($otherUser, $pendingRequest))->toBeFalse()
        ->and($policy->cancel($requester, $completedRequest))->toBeFalse();
});
