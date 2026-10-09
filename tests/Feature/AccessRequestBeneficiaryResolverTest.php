<?php

use App\Models\AccessRequest;
use App\Models\Department;
use App\Models\User;

test('beneficiary resolver returns requester for a self-request', function () {
    Department::create(['name' => 'Resolver Self Department']);
    $requester = User::factory()->create();
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
        'status' => 'pending',
    ]);

    expect($request->resolveBeneficiary()->is($requester))->toBeTrue();
});

test('beneficiary resolver returns the selected internal beneficiary', function () {
    Department::create(['name' => 'Resolver Internal Department']);
    $requester = User::factory()->create();
    $beneficiary = User::factory()->create();
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
        'internal_beneficiary' => $beneficiary->id,
        'status' => 'pending',
    ]);

    expect($request->resolveBeneficiary()->is($beneficiary))->toBeTrue();
});

test('beneficiary resolver returns the external beneficiary name', function () {
    Department::create(['name' => 'Resolver External Department']);
    $requester = User::factory()->create();
    $request = AccessRequest::create([
        'requested_by' => $requester->id,
        'external_beneficiary' => 'Acme Vendor',
        'status' => 'pending',
    ]);

    expect($request->resolveBeneficiary())->toBe('Acme Vendor');
});
