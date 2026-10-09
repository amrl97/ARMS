<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Validation\ValidationException;

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
