<?php

use App\Http\Requests\StoreAccessRequestRequest;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Policies\AccessRequestPolicy;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Route::post('/_test/access-requests', fn (StoreAccessRequestRequest $request) => response()->noContent());
});

function makeRequestEligibilityUser(string $name): User
{
    static $sequence = 0;
    $sequence++;
    $department = Department::create(['name' => "Request Eligibility Department {$sequence}"]);

    $user = User::factory()->create([
        'fullname' => $name,
        'department_id' => $department->id,
    ]);

    return $user;
}

function requestEligibilityFixtures(): array
{
    return [
        Role::create(['name' => 'Staff']),
        Role::create(['name' => 'Admin']),
        Role::create(['name' => 'Super Admin']),
    ];
}

test('an Admin cannot submit an access request', function () {
    [, $adminRole] = requestEligibilityFixtures();
    $admin = makeRequestEligibilityUser('Admin requester');
    $admin->roles()->attach($adminRole);

    $this->actingAs($admin)
        ->postJson('/_test/access-requests')
        ->assertForbidden();

    expect(app(AccessRequestPolicy::class)->create($admin))->toBeFalse();
});

test('a regular user can submit a self-request or request for an eligible internal user', function () {
    [$staffRole] = requestEligibilityFixtures();
    $requester = makeRequestEligibilityUser('Staff requester');
    $requester->roles()->attach($staffRole);
    $beneficiary = makeRequestEligibilityUser('Eligible beneficiary');

    $this->actingAs($requester)
        ->postJson('/_test/access-requests')
        ->assertNoContent();

    $this->postJson('/_test/access-requests', ['internal_beneficiary' => $beneficiary->id])
        ->assertNoContent();
});

test('a Super Admin can make a self-request but cannot be an internal beneficiary', function () {
    [, , $superAdminRole] = requestEligibilityFixtures();
    $superAdmin = makeRequestEligibilityUser('Super Admin requester');
    $superAdmin->roles()->attach($superAdminRole);
    $requester = makeRequestEligibilityUser('Ordinary requester');

    $this->actingAs($superAdmin)
        ->postJson('/_test/access-requests')
        ->assertNoContent();

    $this->actingAs($requester)
        ->postJson('/_test/access-requests', ['internal_beneficiary' => $superAdmin->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('internal_beneficiary');
});

test('Admin and Super Admin users cannot be internal beneficiaries', function () {
    [$staffRole, $adminRole, $superAdminRole] = requestEligibilityFixtures();
    $requester = makeRequestEligibilityUser('Staff requester');
    $requester->roles()->attach($staffRole);
    $admin = makeRequestEligibilityUser('Admin beneficiary');
    $admin->roles()->attach($adminRole);
    $superAdmin = makeRequestEligibilityUser('Super Admin beneficiary');
    $superAdmin->roles()->attach($superAdminRole);

    $this->actingAs($requester)
        ->postJson('/_test/access-requests', ['internal_beneficiary' => $admin->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('internal_beneficiary');

    $this->postJson('/_test/access-requests', ['internal_beneficiary' => $superAdmin->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('internal_beneficiary');

    expect(app(AccessRequestPolicy::class)->selectInternalBeneficiary($requester, $admin))->toBeFalse()
        ->and(app(AccessRequestPolicy::class)->selectInternalBeneficiary($requester, $superAdmin))->toBeFalse();
});

test('request beneficiary validation rejects self-reference and invalid beneficiary combinations', function () {
    [$staffRole] = requestEligibilityFixtures();
    $requester = makeRequestEligibilityUser('Staff requester');
    $requester->roles()->attach($staffRole);

    $this->actingAs($requester)
        ->postJson('/_test/access-requests', ['internal_beneficiary' => $requester->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('internal_beneficiary');

    $this->postJson('/_test/access-requests', [
        'internal_beneficiary' => makeRequestEligibilityUser('Internal beneficiary')->id,
        'external_beneficiary' => 'Vendor Ltd',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['internal_beneficiary', 'external_beneficiary']);
});

test('request beneficiary validation rejects a blank external beneficiary name', function () {
    $request = new StoreAccessRequestRequest();
    $request->merge(['external_beneficiary' => '   ']);
    $validator = Validator::make($request->all(), $request->rules());

    foreach ($request->after() as $callback) {
        $callback($validator);
    }

    expect($validator->errors()->has('external_beneficiary'))->toBeTrue();
});

test('the request create page offers only eligible internal beneficiaries', function () {
    $department = Department::create(['name' => 'Request Selector Department']);
    $staffRole = Role::create(['name' => 'Staff']);
    $adminRole = Role::create(['name' => 'Admin']);
    $superAdminRole = Role::create(['name' => 'Super Admin']);
    $requester = User::factory()->create([
        'fullname' => 'Request Page User',
        'department_id' => $department->id,
    ]);
    $requester->roles()->attach($staffRole);
    $eligible = User::factory()->create([
        'fullname' => 'Eligible Person',
        'department_id' => $department->id,
    ]);
    $eligible->roles()->attach($staffRole);
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->roles()->attach($adminRole);
    $superAdmin = User::factory()->create(['department_id' => $department->id]);
    $superAdmin->roles()->attach($superAdminRole);

    $this->actingAs($requester)
        ->get(route('requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('requests/create')
            ->has('internalBeneficiaries', 1)
            ->where('internalBeneficiaries.0.id', $eligible->id)
            ->where('internalBeneficiaries.0.fullname', 'Eligible Person'));
});
