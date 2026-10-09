<?php

use App\Enums\AccessRequestDetailStatus;
use App\Enums\AccessRequestStatus;
use App\Enums\AccessType;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalStep;
use App\Enums\RequestPriority;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('request and workflow enums expose the agreed controlled values', function () {
    expect(array_column(AccessType::cases(), 'value'))
        ->toBe(['New Staff', 'Privilege', 'Temporary', 'Vendor/3rd Party'])
        ->and(array_column(RequestPriority::cases(), 'value'))
        ->toBe(['Low', 'Medium', 'High'])
        ->and(array_column(AccessRequestStatus::cases(), 'value'))
        ->toBe(['pending', 'in_progress', 'rejected', 'partially_approved', 'approved', 'cancelled'])
        ->and(array_column(AccessRequestDetailStatus::cases(), 'value'))
        ->toBe(['pending', 'approved', 'rejected', 'expired', 'revoked', 'cancelled'])
        ->and(array_column(ApprovalStatus::cases(), 'value'))
        ->toBe(['pending', 'approved', 'rejected', 'skipped'])
        ->and(array_column(ApprovalStep::cases(), 'value'))
        ->toBe(['module', 'final']);
});

test('request header and detail tables reject values outside their controlled sets', function () {
    Department::create(['name' => 'Controlled Values Department']);
    $user = User::factory()->create();
    $module = AccessModuleConfig::create([
        'module_name' => 'Controlled Values Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);
    $request = AccessRequest::create([
        'requested_by' => $user->id,
        'status' => AccessRequestStatus::Pending->value,
    ]);
    $detail = $request->details()->create([
        'module_id' => $module->id,
        'access_type' => AccessType::NewStaff->value,
        'priority' => RequestPriority::Medium->value,
        'status' => AccessRequestDetailStatus::Pending->value,
    ]);

    expect(fn () => DB::table('access_requests')
        ->where('id', $request->id)
        ->update(['status' => 'waiting']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('access_request_details')
        ->where('id', $detail->id)
        ->update(['access_type' => 'Termination/Revoked Access']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('access_request_details')
        ->where('id', $detail->id)
        ->update(['priority' => 'Urgent']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('access_request_details')
        ->where('id', $detail->id)
        ->update(['status' => 'waiting']))
        ->toThrow(QueryException::class);
});
