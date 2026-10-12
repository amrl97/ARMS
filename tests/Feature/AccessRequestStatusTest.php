<?php

use App\Enums\AccessRequestDetailStatus;
use App\Enums\AccessRequestStatus;
use App\Enums\AccessType;
use App\Enums\ApprovalStatus;
use App\Enums\RequestPriority;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\RoleAssignmentService;

function requestWithDetailStatuses(array $detailStatuses): AccessRequest
{
    Department::create(['name' => 'Request Status Department']);
    $requester = User::factory()->create();
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
    ]);

    foreach ($detailStatuses as $index => $status) {
        $module = AccessModuleConfig::create([
            'module_name' => "Request Status Module {$index}",
            'max_approvers' => 0,
            'required_approvals' => 0,
            'is_active' => true,
        ]);

        $request->details()->create([
            'module_id' => $module->id,
            'access_type' => AccessType::NewStaff->value,
            'priority' => RequestPriority::Medium->value,
            'status' => $status->value,
        ]);
    }

    return $request->fresh();
}

test('request header status is calculated from its detail statuses', function (array $detailStatuses, AccessRequestStatus $expectedStatus) {
    $request = requestWithDetailStatuses($detailStatuses);

    expect($request->status)->toBe($expectedStatus->value)
        ->and($request->recalculateStatus())->toBe($expectedStatus)
        ->and($request->fresh()->status)->toBe($expectedStatus->value);
})->with([
    'all pending' => [[AccessRequestDetailStatus::Pending], AccessRequestStatus::Pending],
    'pending with a decision' => [
        [AccessRequestDetailStatus::Pending, AccessRequestDetailStatus::Approved],
        AccessRequestStatus::InProgress,
    ],
    'all rejected' => [[AccessRequestDetailStatus::Rejected], AccessRequestStatus::Rejected],
    'approved and rejected' => [
        [AccessRequestDetailStatus::Approved, AccessRequestDetailStatus::Rejected],
        AccessRequestStatus::PartiallyApproved,
    ],
    'all approved' => [[AccessRequestDetailStatus::Approved], AccessRequestStatus::Approved],
    'expired and revoked count as approved' => [
        [AccessRequestDetailStatus::Expired, AccessRequestDetailStatus::Revoked],
        AccessRequestStatus::Approved,
    ],
    'cancelled lines are ignored' => [
        [AccessRequestDetailStatus::Approved, AccessRequestDetailStatus::Cancelled],
        AccessRequestStatus::Approved,
    ],
    'all cancelled' => [
        [AccessRequestDetailStatus::Cancelled, AccessRequestDetailStatus::Cancelled],
        AccessRequestStatus::Cancelled,
    ],
    'cancelled line with pending line' => [
        [AccessRequestDetailStatus::Cancelled, AccessRequestDetailStatus::Pending],
        AccessRequestStatus::Pending,
    ],
]);

test('request status cannot be mass-assigned and cannot be calculated without details', function () {
    Department::create(['name' => 'Empty Request Status Department']);
    $requester = User::factory()->create();
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
        'status' => AccessRequestStatus::Approved->value,
    ]);

    expect($request->fresh()->status)->toBe(AccessRequestStatus::Pending->value)
        ->and(fn () => $request->recalculateStatus())
        ->toThrow(LogicException::class);
});

test('saving a changed detail status automatically recalculates the header', function () {
    $request = requestWithDetailStatuses([AccessRequestDetailStatus::Pending]);
    $detail = $request->details()->firstOrFail();

    $detail->status = AccessRequestDetailStatus::Approved->value;
    $detail->save();

    expect($request->fresh()->status)->toBe(AccessRequestStatus::Approved->value);
});

test('the daily expiry command marks passed end dates as expired and recalculates the header', function () {
    Department::create(['name' => 'Expiry Department']);
    $requester = User::factory()->create();
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
    ]);
    $module = AccessModuleConfig::create([
        'module_name' => 'Expiry Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);

    $detail = $request->details()->create([
        'module_id' => $module->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Medium->value,
        'status' => AccessRequestDetailStatus::Approved->value,
        'end_date' => now()->subDay()->toDateString(),
    ]);

    $this->artisan('access-requests:expire')
        ->assertSuccessful();

    expect($detail->fresh()->status)->toBe(AccessRequestDetailStatus::Expired->value)
        ->and($request->fresh()->status)->toBe(AccessRequestStatus::Approved->value);
});

test('deactivating an internal beneficiary revokes approved lines and cancels pending lines', function () {
    Department::create(['name' => 'Beneficiary Deactivation Department']);
    $beneficiary = User::factory()->create();
    $requester = User::factory()->create();
    $otherBeneficiary = User::factory()->create();
    $approver = User::factory()->create();
    $moduleNumber = 0;

    $makeDetail = function (
        User $requestedBy,
        ?User $internalBeneficiary,
        ?string $externalBeneficiary,
        AccessRequestDetailStatus $status,
        bool $withPendingApproval = false,
        ?string $endDate = null,
    ) use (&$moduleNumber, $approver): array {
        $moduleNumber++;
        $module = AccessModuleConfig::create([
            'module_name' => "Deactivation Module {$moduleNumber}",
            'max_approvers' => 0,
            'required_approvals' => 0,
            'is_active' => true,
        ]);
        $request = AccessRequest::create([
            'requested_by' => $requestedBy->id,
            'internal_beneficiary' => $internalBeneficiary?->id,
            'external_beneficiary' => $externalBeneficiary,
        ]);
        $detail = $request->details()->create([
            'module_id' => $module->id,
            'access_type' => AccessType::NewStaff->value,
            'priority' => RequestPriority::Medium->value,
            'status' => $status->value,
            'end_date' => $endDate,
        ]);

        if ($withPendingApproval) {
            $detail->approvals()->create([
                'step' => 'module',
                'approver_id' => $approver->id,
                'status' => ApprovalStatus::Pending->value,
            ]);
        }

        return [$request, $detail];
    };

    [$pendingRequest, $pendingDetail] = $makeDetail(
        $requester,
        $beneficiary,
        null,
        AccessRequestDetailStatus::Pending,
        true,
    );
    $pendingApproval = $pendingDetail->approvals()->firstOrFail();
    [$approvedRequest, $approvedDetail] = $makeDetail(
        $requester,
        $beneficiary,
        null,
        AccessRequestDetailStatus::Approved,
    );
    [, $expiredDetail] = $makeDetail(
        $requester,
        $beneficiary,
        null,
        AccessRequestDetailStatus::Expired,
    );
    [, $pastEndDateDetail] = $makeDetail(
        $requester,
        $beneficiary,
        null,
        AccessRequestDetailStatus::Approved,
        endDate: today()->subDay()->toDateString(),
    );
    [, $selfRequestDetail] = $makeDetail(
        $beneficiary,
        null,
        null,
        AccessRequestDetailStatus::Approved,
    );
    [, $requesterOnlyDetail] = $makeDetail(
        $beneficiary,
        $otherBeneficiary,
        null,
        AccessRequestDetailStatus::Pending,
    );
    [, $externalDetail] = $makeDetail(
        $beneficiary,
        null,
        'External vendor',
        AccessRequestDetailStatus::Approved,
    );

    app(RoleAssignmentService::class)->updateUser(
        $beneficiary,
        ['is_active' => false],
        [],
    );

    expect($pendingDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Cancelled->value)
        ->and($pendingApproval->fresh()->status)->toBe(ApprovalStatus::Skipped->value)
        ->and($pendingRequest->fresh()->status)->toBe(AccessRequestStatus::Cancelled->value)
        ->and($approvedDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Revoked->value)
        ->and($approvedRequest->fresh()->status)->toBe(AccessRequestStatus::Approved->value)
        ->and($selfRequestDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Revoked->value)
        ->and($expiredDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Expired->value)
        ->and($pastEndDateDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Approved->value)
        ->and($requesterOnlyDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Pending->value)
        ->and($externalDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Approved->value);
});

test('the requester can cancel only pending lines and skip their pending approvals', function () {
    Department::create(['name' => 'Cancellation Department']);
    $requester = User::factory()->create();
    $wrongUser = User::factory()->create();
    $approver = User::factory()->create();
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
    ]);

    $pendingModule = AccessModuleConfig::create([
        'module_name' => 'Pending Cancel Module',
        'max_approvers' => 1,
        'required_approvals' => 1,
        'is_active' => true,
    ]);
    $approvedModule = AccessModuleConfig::create([
        'module_name' => 'Approved Cancel Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);

    $pendingDetail = $request->details()->create([
        'module_id' => $pendingModule->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Medium->value,
        'status' => AccessRequestDetailStatus::Pending->value,
    ]);
    $pendingApproval = $pendingDetail->approvals()->create([
        'step' => 'module',
        'approver_id' => $approver->id,
        'status' => 'pending',
    ]);

    $approvedDetail = $request->details()->create([
        'module_id' => $approvedModule->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Medium->value,
        'status' => AccessRequestDetailStatus::Approved->value,
    ]);
    $approvedDetail->approvals()->create([
        'step' => 'final',
        'approver_id' => $approver->id,
        'status' => 'approved',
    ]);

    $this->actingAs($wrongUser)
        ->post(route('requests.cancel', $request))
        ->assertForbidden();

    $this->actingAs($requester)
        ->post(route('requests.cancel', $request))
        ->assertRedirect(route('requests.index'));

    expect($pendingDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Cancelled->value)
        ->and($pendingApproval->fresh()->status)->toBe('skipped')
        ->and($approvedDetail->fresh()->status)->toBe(AccessRequestDetailStatus::Approved->value)
        ->and($request->fresh()->status)->toBe(AccessRequestStatus::Approved->value);
});
