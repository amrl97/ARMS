<?php

use App\Models\Department;
use App\Models\AccessModuleConfig;
use App\Models\AccessRequest;
use App\Models\AccessRequestDetail;
use App\Models\Role;
use App\Models\User;
use App\Http\Requests\StoreModuleApproverRequest;
use App\Enums\RequestPriority;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Route::post(
        '/_test/module-approvers',
        fn (StoreModuleApproverRequest $request) => response()->noContent(),
    );
});

test('a valid module config can be created through the admin route', function () {
    Department::create(['name' => 'IT']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/admin/configs', [
            'module_name' => 'Payroll Access',
            'max_approvers' => 2,
            'required_approvals' => 1,
            'is_active' => true,
        ])
        ->assertRedirect('/admin/configs/1/edit')
        ->assertSessionHas('inertia.flash_data.toast', [
            'type' => 'success',
            'message' => 'Module created successfully. Configure its approvers below.',
        ]);

    $this->assertDatabaseHas('access_module_configs', [
        'module_name' => 'Payroll Access',
        'max_approvers' => 2,
        'required_approvals' => 1,
        'is_active' => true,
    ]);
});

test('module configuration listing includes active states and paginates all modules', function () {
    Department::create(['name' => 'Module Configuration']);
    $user = User::factory()->create();

    foreach (range(1, 11) as $number) {
        AccessModuleConfig::create([
            'module_name' => "Module {$number}",
            'max_approvers' => 0,
            'required_approvals' => 0,
            'is_active' => $number % 2 === 0,
        ]);
    }

    $this->actingAs($user)
        ->get('/admin/configs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Configs/index')
            ->has('configs.data', 10)
            ->has('configs.links', 4)
            ->where('configs.data.0.module_name', 'Module 11')
            ->where('configs.data.0.is_active', false));

    $this->get('/admin/configs?page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Configs/index')
            ->has('configs.data', 1)
            ->where('configs.data.0.module_name', 'Module 1'));
});

test('module configuration has separate index, create, view, and edit pages', function () {
    Department::create(['name' => 'Module Pages']);
    $user = User::factory()->create();
    $module = AccessModuleConfig::create([
        'module_name' => 'Page Test Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get('/admin/configs')
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Configs/index'));

    $this->get('/admin/configs/create')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Configs/create')
            ->has('approverRoles')
            ->has('approverUsers')
            ->has('departments'));

    $this->get("/admin/configs/{$module->id}")
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Configs/view'));

    $this->get("/admin/configs/{$module->id}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Configs/edit')
            ->where('config.module_name', 'Page Test Module')
            ->has('approverRoles')
            ->has('approverUsers')
            ->has('departments'));
});

test('a module with no module approvers can be created with zero required approvals', function () {
    Department::create(['name' => 'Finance']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/admin/configs', [
            'module_name' => 'Final Approval Only',
            'max_approvers' => 0,
            'required_approvals' => 0,
            'is_active' => true,
        ])
        ->assertRedirect('/admin/configs/1/edit');

    $this->assertDatabaseHas('access_module_configs', [
        'module_name' => 'Final Approval Only',
        'max_approvers' => 0,
        'required_approvals' => 0,
    ]);
});

test('module creation can save settings and initial approver rules together', function () {
    $department = Department::create(['name' => 'Initial Approver Department']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $role = Role::create(['name' => 'Initial Approver Role']);

    $this->actingAs($user)
        ->post('/admin/configs', [
            'module_name' => 'Module with Initial Approver',
            'max_approvers' => 1,
            'required_approvals' => 1,
            'is_active' => true,
            'approvers' => [[
                'user_id' => null,
                'role_id' => $role->id,
                'department_id' => $department->id,
                'type' => 'role',
            ]],
        ])
        ->assertRedirect('/admin/configs/1/edit');

    $module = AccessModuleConfig::where('module_name', 'Module with Initial Approver')->firstOrFail();
    $this->assertDatabaseHas('module_approvers', [
        'module_id' => $module->id,
        'role_id' => $role->id,
        'department_id' => $department->id,
    ]);
});

test('module creation rejects more initial approver rules than its configured maximum', function () {
    $department = Department::create(['name' => 'Initial Capacity Department']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $firstRole = Role::create(['name' => 'First Initial Role']);
    $secondRole = Role::create(['name' => 'Second Initial Role']);

    $this->actingAs($user)
        ->from('/admin/configs/create')
        ->post('/admin/configs', [
            'module_name' => 'Over Capacity Initial Module',
            'max_approvers' => 1,
            'required_approvals' => 1,
            'is_active' => true,
            'approvers' => [
                ['role_id' => $firstRole->id],
                ['role_id' => $secondRole->id],
            ],
        ])
        ->assertSessionHasErrors('approvers');

    $this->assertDatabaseMissing('access_module_configs', [
        'module_name' => 'Over Capacity Initial Module',
    ]);
});

test('invalid module approval count combinations are rejected before the write happens', function (
    int $maxApprovers,
    int $requiredApprovals,
    string $errorField,
) {
    Department::create(['name' => 'Finance']);
    $user = User::factory()->create();
    $moduleName = "Invalid Module {$maxApprovers}-{$requiredApprovals}";

    $this->actingAs($user)
        ->from('/admin/configs')
        ->post('/admin/configs', [
            'module_name' => $moduleName,
            'max_approvers' => $maxApprovers,
            'required_approvals' => $requiredApprovals,
            'is_active' => true,
        ])
        ->assertSessionHasErrors($errorField);

    $this->assertDatabaseMissing('access_module_configs', [
        'module_name' => $moduleName,
    ]);
})->with([
    'negative maximum' => [-1, 0, 'max_approvers'],
    'zero maximum requires zero approvals' => [0, 1, 'required_approvals'],
    'positive maximum requires one approval' => [2, 0, 'required_approvals'],
    'positive maximum cannot require multiple approvals' => [2, 2, 'required_approvals'],
]);

test('database checks protect module and approver configuration invariants', function () {
    expect(fn () => DB::table('access_module_configs')->insert([
        'module_name' => 'Invalid Module',
        'max_approvers' => -1,
        'required_approvals' => 0,
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $module = AccessModuleConfig::create([
        'module_name' => 'Valid Module',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => 1,
    ]);

    expect(fn () => DB::table('module_approvers')->insert([
        'module_id' => $module->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $department = Department::create(['name' => 'Approver Rule Department']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $role = Role::create(['name' => 'Approver Rule Role']);

    expect(fn () => DB::table('module_approvers')->insert([
        'module_id' => $module->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('module_approvers')->insert([
        'module_id' => $module->id,
        'user_id' => $user->id,
        'department_id' => $department->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    DB::table('module_approvers')->insert([
        'module_id' => $module->id,
        'role_id' => $role->id,
        'department_id' => $department->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

test('module active state can be changed while updating its approval settings', function () {
    Department::create(['name' => 'Operations']);
    $user = User::factory()->create();
    $module = AccessModuleConfig::create([
        'module_name' => 'Operations Portal',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->put("/admin/configs/{$module->id}", [
            'module_name' => 'Operations Portal',
            'max_approvers' => 2,
            'required_approvals' => 1,
            'is_active' => false,
        ])
        ->assertRedirect("/admin/configs/{$module->id}/edit")
        ->assertSessionHas('inertia.flash_data.toast', [
            'type' => 'success',
            'message' => 'Module updated successfully.',
        ]);

    $module->refresh();

    expect($module->is_active)->toBeFalse()
        ->and($module->max_approvers)->toBe(2)
        ->and($module->required_approvals)->toBe(1);
});

test('a module maximum cannot be reduced below its configured approver-row count', function () {
    Department::create(['name' => 'Capacity Test']);
    $user = User::factory()->create();
    $module = AccessModuleConfig::create([
        'module_name' => 'Capacity Protected Module',
        'max_approvers' => 2,
        'required_approvals' => 1,
        'is_active' => true,
    ]);

    DB::table('module_approvers')->insert([
        'module_id' => $module->id,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->from('/admin/configs')
        ->put("/admin/configs/{$module->id}", [
            'module_name' => $module->module_name,
            'max_approvers' => 0,
            'required_approvals' => 0,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('max_approvers');

    expect($module->fresh()->max_approvers)->toBe(2);
});

test('module approver creation is rejected when the module row limit is reached', function () {
    Department::create(['name' => 'Approver Capacity']);
    $user = User::factory()->create();
    $module = AccessModuleConfig::create([
        'module_name' => 'One Approver Module',
        'max_approvers' => 1,
        'required_approvals' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post('/_test/module-approvers', [
            'module_id' => $module->id,
            'user_id' => $user->id,
        ])
        ->assertNoContent();

    DB::table('module_approvers')->insert([
        'module_id' => $module->id,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson('/_test/module-approvers', [
        'module_id' => $module->id,
        'user_id' => $user->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('module_id');
});

test('module approver validation accepts either a specific user or a role', function () {
    $department = Department::create(['name' => 'Approver Rule Validation']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $role = Role::create(['name' => 'Finance Approver']);
    $module = AccessModuleConfig::create([
        'module_name' => 'Valid Approver Rule Module',
        'max_approvers' => 2,
        'required_approvals' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/_test/module-approvers', [
            'module_id' => $module->id,
            'user_id' => $user->id,
        ])
        ->assertNoContent();

    $this->postJson('/_test/module-approvers', [
        'module_id' => $module->id,
        'role_id' => $role->id,
        'department_id' => $department->id,
    ])
        ->assertNoContent();
});

test('module approver validation rejects a department on a specific-user rule', function () {
    $department = Department::create(['name' => 'Specific User Department']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $module = AccessModuleConfig::create([
        'module_name' => 'Specific User Department Module',
        'max_approvers' => 1,
        'required_approvals' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/_test/module-approvers', [
            'module_id' => $module->id,
            'user_id' => $user->id,
            'department_id' => $department->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('department_id');
});

test('Admin and Super Admin users and roles cannot be module approvers', function () {
    $department = Department::create(['name' => 'Restricted Approver Department']);
    $module = AccessModuleConfig::create([
        'module_name' => 'Restricted Approver Module',
        'max_approvers' => 4,
        'required_approvals' => 1,
        'is_active' => true,
    ]);
    $actor = User::factory()->create(['department_id' => $department->id]);
    $this->actingAs($actor);

    foreach (User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES as $restrictedRoleName) {
        $restrictedRole = Role::create(['name' => $restrictedRoleName]);
        $restrictedUser = User::factory()->create(['department_id' => $department->id]);
        $restrictedUser->roles()->attach($restrictedRole);

        $this->postJson('/_test/module-approvers', [
            'module_id' => $module->id,
            'user_id' => $restrictedUser->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->postJson('/_test/module-approvers', [
            'module_id' => $module->id,
            'role_id' => $restrictedRole->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');
    }
});

test('module approver validation rejects both or neither user and role', function () {
    $department = Department::create(['name' => 'Invalid Approver Rule']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $role = Role::create(['name' => 'Invalid Rule Role']);
    $module = AccessModuleConfig::create([
        'module_name' => 'Invalid Approver Rule Module',
        'max_approvers' => 2,
        'required_approvals' => 1,
        'is_active' => true,
    ]);

    foreach ([
        [
            'user_id' => $user->id,
            'role_id' => $role->id,
        ],
        [],
    ] as $rule) {
        $this->actingAs($user)
            ->postJson('/_test/module-approvers', [
            'module_id' => $module->id,
                ...$rule,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }
});

test('deactivating a module excludes it from request choices and preserves existing request history', function () {
    Department::create(['name' => 'Service Desk']);
    $requester = User::factory()->create();
    $module = AccessModuleConfig::create([
        'module_name' => 'Service Desk Portal',
        'max_approvers' => 0,
        'required_approvals' => 0,
        'is_active' => true,
    ]);
    $accessRequest = AccessRequest::create([
        'requested_by' => $requester->id,
        'status' => 'pending',
    ]);
    $detail = $accessRequest->details()->create([
        'module_id' => $module->id,
        'access_type' => 'New Staff',
        'priority' => RequestPriority::Medium->value,
        'status' => 'pending',
    ]);

    expect(AccessModuleConfig::availableForRequests()->pluck('id')->all())
        ->toContain($module->id);

    $module->update(['is_active' => false]);

    expect(AccessModuleConfig::availableForRequests()->pluck('id')->all())
        ->not->toContain($module->id);

    $this->assertDatabaseHas('access_request_details', [
        'id' => $detail->id,
        'module_id' => $module->id,
        'request_id' => $accessRequest->id,
        'status' => 'pending',
    ]);
    expect($detail->refresh()->module->module_name)->toBe('Service Desk Portal');
});
