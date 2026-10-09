<?php

use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\AccessRequestApproval;
use App\Models\Department;
use App\Models\User;
use App\Enums\RequestPriority;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function approvalSchemaFixture(): array
{
    Department::create(['name' => 'Approval Test Department']);
    $approver = User::factory()->create();
    $module = AccessModuleConfig::create([
        'module_name' => 'Approval Test Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);
    $request = AccessRequest::create([
        'requested_by' => $approver->id,
        'status' => 'pending',
    ]);
    $detail = $request->details()->create([
        'module_id' => $module->id,
        'access_type' => 'New Staff',
        'priority' => RequestPriority::Medium->value,
        'status' => 'pending',
    ]);

    return [$approver, $detail];
}

test('approval rows store an assigned user and explicit workflow state with optional rejection reason', function () {
    [$approver, $detail] = approvalSchemaFixture();

    expect(DB::getSchemaBuilder()->hasColumn('access_request_approvals', 'module_approver_id'))
        ->toBeFalse();

    $approval = $detail->approvals()->create([
        'approver_id' => $approver->id,
        'step' => 'final',
        'status' => 'pending',
    ]);

    expect($approval->rejection_reason)->toBeNull()
        ->and($approval->requestDetail->is($detail))->toBeTrue()
        ->and($approval->approver->is($approver))->toBeTrue()
        ->and($approver->approvals()->whereKey($approval->id)->exists())->toBeTrue();
});

test('database constraints reject missing approver and invalid approval step or status', function () {
    [$approver, $detail] = approvalSchemaFixture();

    expect(fn () => DB::table('access_request_approvals')->insert([
        'request_detail_id' => $detail->id,
        'step' => 'final',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $invalidStep = [
        'request_detail_id' => $detail->id,
        'approver_id' => $approver->id,
        'step' => 'other',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ];
    expect(fn () => DB::table('access_request_approvals')->insert($invalidStep))
        ->toThrow(QueryException::class);

    $invalidStatus = [
        ...$invalidStep,
        'step' => 'module',
        'status' => 'waiting',
    ];
    expect(fn () => DB::table('access_request_approvals')->insert($invalidStatus))
        ->toThrow(QueryException::class);
});
