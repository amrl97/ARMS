<?php

use App\Enums\AccessRequestDetailStatus;
use App\Enums\AccessRequestStatus;
use App\Enums\AccessType;
use App\Enums\RequestPriority;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\Department;
use App\Models\User;

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
