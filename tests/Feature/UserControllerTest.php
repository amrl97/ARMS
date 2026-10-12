<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function userManagementFixtures(): array
{
    return [
        Department::create(['name' => 'User Management Department']),
        Role::create(['name' => 'Staff']),
        Role::create(['name' => 'Admin']),
    ];
}

test('user accounts are listed with pagination and are managed through activation status', function () {
    [$department, $staff] = userManagementFixtures();
    $actingUser = User::factory()->create(['department_id' => $department->id]);
    $actingUser->roles()->attach($staff);

    $this->actingAs($actingUser)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.is_active', true)
            ->where('users.data.0.department.name', $department->name)
            ->where('users.data.0.roles.0.name', $staff->name)
            ->has('users.links'));

    $this->delete("/users/{$actingUser->id}")->assertMethodNotAllowed();
    $this->assertDatabaseHas('users', ['id' => $actingUser->id]);
});

test('a user can only be created with one department and at least one role', function () {
    [$department, $staff, $admin] = userManagementFixtures();
    $actingUser = User::factory()->create(['department_id' => $department->id]);

    $this->actingAs($actingUser);
    $this->get(route('users.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/users/create')
            ->has('departments', 1)
            ->has('roles', 2));

    $this->post(route('users.store'), [
        'fullname' => 'New User',
        'email' => 'new.user@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'department_id' => $department->id,
        'role_ids' => [$staff->id, $admin->id],
        'is_active' => 1,
    ])
        ->assertRedirect(route('users.index'));

    $createdUser = User::query()->where('email', 'new.user@example.com')->firstOrFail();

    expect($createdUser->department_id)->toBe($department->id)
        ->and($createdUser->roles->modelKeys())->toEqualCanonicalizing([$staff->id, $admin->id]);
});

test('user creation rejects a missing department or an empty role assignment', function () {
    [$department] = userManagementFixtures();
    $actingUser = User::factory()->create(['department_id' => $department->id]);

    $this->actingAs($actingUser)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'fullname' => 'No Department',
            'email' => 'no.department@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => 1,
        ])
        ->assertSessionHasErrors('department_id');

    $this->from(route('users.create'))
        ->post(route('users.store'), [
            'fullname' => 'No Role',
            'email' => 'no.role@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'department_id' => $department->id,
            'role_ids' => [],
            'is_active' => 1,
        ])
        ->assertSessionHasErrors('role_ids');

    $this->assertDatabaseMissing('users', ['email' => 'no.department@example.com']);
    $this->assertDatabaseMissing('users', ['email' => 'no.role@example.com']);
});

test('user form request validates email, password confirmation, and active status', function () {
    [$department, $staff] = userManagementFixtures();
    $existingUser = User::factory()->create([
        'department_id' => $department->id,
        'email' => 'existing.user@example.com',
    ]);
    $actingUser = User::factory()->create(['department_id' => $department->id]);

    $this->actingAs($actingUser)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'fullname' => 'Invalid User',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
            'department_id' => $department->id,
            'role_ids' => [$staff->id],
            'is_active' => 'enabled',
        ])
        ->assertSessionHasErrors(['email', 'password', 'is_active']);

    $this->from(route('users.edit', $existingUser))
        ->put(route('users.update', $existingUser), [
            'fullname' => $existingUser->fullname,
            'email' => 'not-an-email',
            'password' => '',
            'password_confirmation' => '',
            'department_id' => $department->id,
            'role_ids' => [$staff->id],
            'is_active' => 1,
        ])
        ->assertSessionHasErrors('email');
});

test('only Admin users may create inactive users or deactivate active users', function () {
    [$department, $staff, $admin] = userManagementFixtures();
    $ordinaryUser = User::factory()->create(['department_id' => $department->id]);
    $adminUser = User::factory()->create(['department_id' => $department->id]);
    $adminUser->roles()->attach($admin);
    $targetUser = User::factory()->create(['department_id' => $department->id]);
    $targetUser->roles()->attach($staff);

    $newInactiveUser = [
        'fullname' => 'Inactive New User',
        'email' => 'inactive.new@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'department_id' => $department->id,
        'role_ids' => [$staff->id],
        'is_active' => 0,
    ];

    $this->actingAs($ordinaryUser)
        ->post(route('users.store'), $newInactiveUser)
        ->assertForbidden();

    $this->put(route('users.update', $targetUser), [
        'fullname' => $targetUser->fullname,
        'email' => $targetUser->email,
        'password' => '',
        'password_confirmation' => '',
        'department_id' => $department->id,
        'role_ids' => [$staff->id],
        'is_active' => 0,
    ])->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'inactive.new@example.com']);
    $this->assertDatabaseHas('users', ['id' => $targetUser->id, 'is_active' => true]);

    $this->actingAs($adminUser)
        ->post(route('users.store'), $newInactiveUser)
        ->assertRedirect(route('users.index'));

    $this->put(route('users.update', $targetUser), [
        'fullname' => $targetUser->fullname,
        'email' => $targetUser->email,
        'password' => '',
        'password_confirmation' => '',
        'department_id' => $department->id,
        'role_ids' => [$staff->id],
        'is_active' => 0,
    ])->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', ['email' => 'inactive.new@example.com', 'is_active' => false]);
    $this->assertDatabaseHas('users', ['id' => $targetUser->id, 'is_active' => false]);
});

test('a user update cannot remove all roles and can change the department', function () {
    [$department, $staff, $admin] = userManagementFixtures();
    $otherDepartment = Department::create(['name' => 'Other Department']);
    $actingUser = User::factory()->create(['department_id' => $department->id]);
    $user = User::factory()->create(['department_id' => $department->id]);
    $user->roles()->attach($staff);

    $this->actingAs($actingUser);
    $this->get(route('users.edit', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/users/edit')
            ->where('userRoleIds.0', $staff->id));

    $this->from(route('users.edit', $user))
        ->put(route('users.update', $user), [
            'fullname' => $user->fullname,
            'email' => $user->email,
            'password' => '',
            'password_confirmation' => '',
            'department_id' => $otherDepartment->id,
            'role_ids' => [],
            'is_active' => 1,
        ])
        ->assertSessionHasErrors('role_ids');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'department_id' => $department->id,
    ]);
    $this->assertDatabaseHas('user_has_roles', [
        'user_id' => $user->id,
        'role_id' => $staff->id,
    ]);

    $this->put(route('users.update', $user), [
        'fullname' => $user->fullname,
        'email' => $user->email,
        'password' => '',
        'password_confirmation' => '',
        'department_id' => $otherDepartment->id,
        'role_ids' => [$admin->id],
        'is_active' => 1,
    ])->assertRedirect(route('users.index'));

    $user->refresh();
    expect($user->department_id)->toBe($otherDepartment->id)
        ->and($user->roles->modelKeys())->toBe([$admin->id]);
});

test('user changes cannot assign a second active head of department to a department', function () {
    [$department, $staff] = userManagementFixtures();
    $headOfDepartment = Role::create(['name' => 'Head of Department']);
    $actingUser = User::factory()->create(['department_id' => $department->id]);
    $existingHead = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
    $existingHead->roles()->attach($headOfDepartment);

    $this->actingAs($actingUser)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'fullname' => 'Second Head',
            'email' => 'second.head@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'department_id' => $department->id,
            'role_ids' => [$staff->id, $headOfDepartment->id],
            'is_active' => 1,
        ])
        ->assertSessionHasErrors('role_ids');

    $this->assertDatabaseMissing('users', ['email' => 'second.head@example.com']);
});

test('role assignment service rejects an active second HOD and allows an inactive HOD', function () {
    [$department, $staff] = userManagementFixtures();
    $headOfDepartment = Role::create(['name' => 'Head of Department']);
    $existingHead = User::factory()->create(['department_id' => $department->id]);
    $existingHead->roles()->attach($headOfDepartment);

    $candidate = User::factory()->create(['department_id' => $department->id, 'is_active' => false]);
    $secondActiveUser = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
    $service = app(RoleAssignmentService::class);

    $service->assignRoles($candidate, [$headOfDepartment->id]);
    expect($candidate->fresh()->roles->modelKeys())->toBe([$headOfDepartment->id]);

    $service->assignRoles($secondActiveUser, [$staff->id]);
    expect(fn () => $service->assignRoles($secondActiveUser, [$staff->id, $headOfDepartment->id]))
        ->toThrow(ValidationException::class);
    expect($secondActiveUser->fresh()->roles->modelKeys())->toBe([$staff->id]);
});

test('role assignment service checks HOD uniqueness on reactivation and department changes', function () {
    [$department, $staff] = userManagementFixtures();
    $targetDepartment = Department::create(['name' => 'HOD Target Department']);
    $headOfDepartment = Role::create(['name' => 'Head of Department']);
    $existingHead = User::factory()->create(['department_id' => $department->id]);
    $existingHead->roles()->attach($headOfDepartment);

    $candidate = User::factory()->create([
        'department_id' => $department->id,
        'is_active' => false,
    ]);
    $service = app(RoleAssignmentService::class);
    $service->assignRoles($candidate, [$headOfDepartment->id]);

    expect(fn () => $service->updateUser($candidate, ['is_active' => true], [$headOfDepartment->id]))
        ->toThrow(ValidationException::class);
    expect((bool) $candidate->fresh()->is_active)->toBeFalse();

    $service->assignRoles($existingHead, [$staff->id]);
    $service->updateUser($candidate, ['is_active' => true], [$headOfDepartment->id]);
    expect((bool) $candidate->fresh()->is_active)->toBeTrue();

    $otherHead = User::factory()->create(['department_id' => $targetDepartment->id]);
    $otherHead->roles()->attach($headOfDepartment);

    expect(fn () => $service->updateUser(
        $candidate,
        ['department_id' => $targetDepartment->id],
        [$headOfDepartment->id],
    ))->toThrow(ValidationException::class);
    expect($candidate->fresh()->department_id)->toBe($department->id);
});

test('role assignment service rolls user changes back when role synchronization fails', function () {
    [$department, $staff] = userManagementFixtures();
    $user = User::factory()->create([
        'department_id' => $department->id,
        'fullname' => 'Before Update',
    ]);
    $user->roles()->attach($staff);

    expect(fn () => app(RoleAssignmentService::class)->updateUser(
        $user,
        ['fullname' => 'Uncommitted Update'],
        [PHP_INT_MAX],
    ))->toThrow(QueryException::class);

    expect($user->fresh()->fullname)->toBe('Before Update')
        ->and($user->fresh()->roles->modelKeys())->toBe([$staff->id]);
});
