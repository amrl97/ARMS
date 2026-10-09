<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('request beneficiary columns support self, internal, and external beneficiaries', function () {
    Department::create(['name' => 'Beneficiary Schema Department']);
    $requester = User::factory()->create();
    $internalBeneficiary = User::factory()->create();
    $timestamps = [
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $selfRequestId = DB::table('access_requests')->insertGetId([
        'requested_by' => $requester->id,
        'internal_beneficiary' => null,
        'external_beneficiary' => null,
        'status' => 'pending',
        ...$timestamps,
    ]);

    $internalRequestId = DB::table('access_requests')->insertGetId([
        'requested_by' => $requester->id,
        'internal_beneficiary' => $internalBeneficiary->id,
        'external_beneficiary' => null,
        'status' => 'pending',
        ...$timestamps,
    ]);

    $externalRequestId = DB::table('access_requests')->insertGetId([
        'requested_by' => $requester->id,
        'internal_beneficiary' => null,
        'external_beneficiary' => 'Acme Vendor',
        'status' => 'pending',
        ...$timestamps,
    ]);

    $this->assertDatabaseHas('access_requests', [
        'id' => $selfRequestId,
        'internal_beneficiary' => null,
        'external_beneficiary' => null,
    ]);
    $this->assertDatabaseHas('access_requests', [
        'id' => $internalRequestId,
        'internal_beneficiary' => $internalBeneficiary->id,
        'external_beneficiary' => null,
    ]);
    $this->assertDatabaseHas('access_requests', [
        'id' => $externalRequestId,
        'internal_beneficiary' => null,
        'external_beneficiary' => 'Acme Vendor',
    ]);
});

test('request beneficiary constraints reject conflicting or blank beneficiary values', function () {
    Department::create(['name' => 'Beneficiary Constraint Department']);
    $requester = User::factory()->create();
    $internalBeneficiary = User::factory()->create();
    $timestamps = [
        'created_at' => now(),
        'updated_at' => now(),
    ];

    expect(fn () => DB::table('access_requests')->insert([
        'requested_by' => $requester->id,
        'internal_beneficiary' => $internalBeneficiary->id,
        'external_beneficiary' => 'Acme Vendor',
        'status' => 'pending',
        ...$timestamps,
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('access_requests')->insert([
        'requested_by' => $requester->id,
        'internal_beneficiary' => $requester->id,
        'external_beneficiary' => null,
        'status' => 'pending',
        ...$timestamps,
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('access_requests')->insert([
        'requested_by' => $requester->id,
        'internal_beneficiary' => null,
        'external_beneficiary' => '   ',
        'status' => 'pending',
        ...$timestamps,
    ]))->toThrow(QueryException::class);
});
