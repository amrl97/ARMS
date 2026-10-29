<?php

use App\Enums\AccessType;
use App\Enums\RequestPriority;
use App\Http\Requests\StoreAccessRequestRequest;
use App\Models\AccessModuleConfig;
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

function validRequestEligibilityPayload(array $overrides = []): array
{
    static $sequence = 0;
    $sequence++;
    $module = AccessModuleConfig::create([
        'module_name' => "Eligible Request Module {$sequence}",
        'is_active' => true,
    ]);

    return array_replace([
        'beneficiary_type' => 'self',
        'internal_beneficiary' => null,
        'external_beneficiary' => null,
        'module_lines' => [[
            'module_id' => $module->id,
            'access_type' => AccessType::NewStaff->value,
            'priority' => RequestPriority::Medium->value,
            'justification' => null,
            'start_date' => null,
            'end_date' => null,
        ]],
    ], $overrides);
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
        ->postJson('/_test/access-requests', validRequestEligibilityPayload())
        ->assertNoContent();

    $this->postJson('/_test/access-requests', validRequestEligibilityPayload([
        'beneficiary_type' => 'internal',
        'internal_beneficiary' => $beneficiary->id,
    ]))
        ->assertNoContent();
});

test('request module lines accept active modules and reject inactive or unavailable modules', function () {
    [$staffRole] = requestEligibilityFixtures();
    $requester = makeRequestEligibilityUser('Staff requester');
    $requester->roles()->attach($staffRole);
    $activeModule = AccessModuleConfig::create([
        'module_name' => 'Active Request Module',
        'is_active' => true,
    ]);
    $inactiveModule = AccessModuleConfig::create([
        'module_name' => 'Inactive Request Module',
        'is_active' => false,
    ]);

    $this->actingAs($requester)
        ->postJson('/_test/access-requests', [
            'beneficiary_type' => 'self',
            'module_lines' => [[
                'module_id' => $activeModule->id,
                'access_type' => AccessType::NewStaff->value,
                'priority' => RequestPriority::Medium->value,
            ]],
        ])
        ->assertNoContent();

    $this->postJson('/_test/access-requests', [
        'module_lines' => [['module_id' => $inactiveModule->id]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('module_lines.0.module_id')
        ->assertSeeText('A selected module is inactive or unavailable. Choose an active module or remove it from the request.');

    $this->postJson('/_test/access-requests', [
        'module_lines' => [['module_id' => 999999]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('module_lines.0.module_id');
});

test('request module lines reject invalid access types, priorities, and date formats', function () {
    [$staffRole] = requestEligibilityFixtures();
    $requester = makeRequestEligibilityUser('Invalid request values requester');
    $requester->roles()->attach($staffRole);

    $invalidLines = [
        'access type' => ['value' => 'Termination/Revoked Access', 'path' => 'access_type'],
        'priority' => ['value' => 'Urgent', 'path' => 'priority'],
        'start date' => ['value' => '12/10/2026', 'path' => 'start_date'],
        'end date' => ['value' => 'not-a-date', 'path' => 'end_date'],
    ];

    foreach ($invalidLines as $invalidValue) {
        $payload = validRequestEligibilityPayload();
        $payload['module_lines'][0][$invalidValue['path']] = $invalidValue['value'];

        $this->actingAs($requester)
            ->postJson('/_test/access-requests', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors("module_lines.0.{$invalidValue['path']}");
    }
});

test('Vendor/3rd Party access type requires an external beneficiary', function () {
    [$staffRole] = requestEligibilityFixtures();
    $requester = makeRequestEligibilityUser('Vendor access requester');
    $requester->roles()->attach($staffRole);
    $payload = validRequestEligibilityPayload();
    $payload['module_lines'][0]['access_type'] = AccessType::VendorThirdParty->value;

    $this->actingAs($requester)
        ->postJson('/_test/access-requests', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('module_lines.0.access_type');

    $payload['beneficiary_type'] = 'external';
    $payload['external_beneficiary'] = 'Vendor Ltd';

    $this->postJson('/_test/access-requests', $payload)->assertNoContent();
});

test('request module line end dates cannot be before start dates', function () {
    [$staffRole] = requestEligibilityFixtures();
    $requester = makeRequestEligibilityUser('Staff requester');
    $requester->roles()->attach($staffRole);
    $module = AccessModuleConfig::create([
        'module_name' => 'Date Validation Module',
        'is_active' => true,
    ]);

    $this->actingAs($requester)
        ->postJson('/_test/access-requests', [
            'beneficiary_type' => 'self',
            'module_lines' => [[
                'module_id' => $module->id,
                'access_type' => AccessType::Temporary->value,
                'priority' => RequestPriority::Medium->value,
                'start_date' => '2026-10-12',
                'end_date' => '2026-10-12',
            ]],
        ])
        ->assertNoContent();

    $this->postJson('/_test/access-requests', [
        'beneficiary_type' => 'self',
        'module_lines' => [[
            'module_id' => $module->id,
            'access_type' => AccessType::Temporary->value,
            'priority' => RequestPriority::Medium->value,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-13',
        ]],
    ])->assertNoContent();

    $this->postJson('/_test/access-requests', [
        'beneficiary_type' => 'self',
        'module_lines' => [[
            'module_id' => $module->id,
            'access_type' => AccessType::Temporary->value,
            'priority' => RequestPriority::Medium->value,
            'end_date' => '2026-10-12',
        ]],
    ])->assertNoContent();

    $this->postJson('/_test/access-requests', [
        'beneficiary_type' => 'self',
        'module_lines' => [[
            'module_id' => $module->id,
            'access_type' => AccessType::Temporary->value,
            'priority' => RequestPriority::Medium->value,
            'start_date' => '2026-10-12',
        ]],
    ])->assertNoContent();

    $this->postJson('/_test/access-requests', [
        'beneficiary_type' => 'self',
        'module_lines' => [[
            'module_id' => $module->id,
            'access_type' => AccessType::Temporary->value,
            'priority' => RequestPriority::Medium->value,
            'start_date' => '2026-10-13',
            'end_date' => '2026-10-12',
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('module_lines.0.end_date')
        ->assertSeeText('The end date must be on or after the start date.');
});

test('a Super Admin can make a self-request but cannot be an internal beneficiary', function () {
    [, , $superAdminRole] = requestEligibilityFixtures();
    $superAdmin = makeRequestEligibilityUser('Super Admin requester');
    $superAdmin->roles()->attach($superAdminRole);
    $requester = makeRequestEligibilityUser('Ordinary requester');

    $this->actingAs($superAdmin)
        ->postJson('/_test/access-requests', validRequestEligibilityPayload())
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
    $mixedRoleUser = makeRequestEligibilityUser('Restricted role with Staff');
    $mixedRoleUser->roles()->attach([$staffRole->id, $adminRole->id]);

    $this->actingAs($requester)
        ->postJson('/_test/access-requests', ['internal_beneficiary' => $admin->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('internal_beneficiary');

    $this->postJson('/_test/access-requests', ['internal_beneficiary' => $superAdmin->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('internal_beneficiary');

    $this->postJson('/_test/access-requests', ['internal_beneficiary' => $mixedRoleUser->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('internal_beneficiary');

    expect(app(AccessRequestPolicy::class)->selectInternalBeneficiary($requester, $admin))->toBeFalse()
        ->and(app(AccessRequestPolicy::class)->selectInternalBeneficiary($requester, $superAdmin))->toBeFalse()
        ->and(app(AccessRequestPolicy::class)->selectInternalBeneficiary($requester, $mixedRoleUser))->toBeFalse();
});

test('inactive users cannot be selected as internal beneficiaries', function () {
    [$staffRole] = requestEligibilityFixtures();
    $requester = makeRequestEligibilityUser('Active requester');
    $requester->roles()->attach($staffRole);
    $inactive = makeRequestEligibilityUser('Inactive beneficiary');
    $inactive->forceFill(['is_active' => false])->save();
    $inactive->roles()->attach($staffRole);

    $this->actingAs($requester)
        ->postJson('/_test/access-requests', ['internal_beneficiary' => $inactive->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('internal_beneficiary');

    expect(app(AccessRequestPolicy::class)->selectInternalBeneficiary($requester, $inactive))->toBeFalse();
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
    $request = new StoreAccessRequestRequest;
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
    $inactive = User::factory()->create([
        'fullname' => 'Inactive Person',
        'department_id' => $department->id,
        'is_active' => false,
    ]);
    $inactive->roles()->attach($staffRole);
    $mixedRole = User::factory()->create([
        'fullname' => 'Restricted Role Person',
        'department_id' => $department->id,
    ]);
    $mixedRole->roles()->attach([$staffRole->id, $adminRole->id]);

    $activeModule = AccessModuleConfig::create([
        'module_name' => 'Active Request Module',
        'is_active' => true,
    ]);
    AccessModuleConfig::create([
        'module_name' => 'Inactive Request Module',
        'is_active' => false,
    ]);

    $this->actingAs($requester)
        ->get(route('requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('requests/create')
            ->where('accessTypes', array_map(
                static fn (AccessType $accessType): string => $accessType->value,
                AccessType::cases(),
            ))
            ->has('modules', 1)
            ->where('modules.0.id', $activeModule->id)
            ->where('modules.0.module_name', 'Active Request Module')
            ->has('internalBeneficiaries', 1)
            ->where('internalBeneficiaries.0.id', $eligible->id)
            ->where('internalBeneficiaries.0.fullname', 'Eligible Person')
            ->missing('internalBeneficiaries.1')
            ->missing('internalBeneficiaries.2')
            ->where('priorities', array_map(
                static fn (RequestPriority $priority): string => $priority->value,
                RequestPriority::cases(),
            )));
});
