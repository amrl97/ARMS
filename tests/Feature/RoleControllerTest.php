<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function createRoleTestUser(): User
{
    $departmentId = DB::table('departments')->insertGetId([
        'name' => 'Role Test Department',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return User::factory()->create([
        'department_id' => $departmentId,
    ]);
}

test('an authenticated user can manage roles', function () {
    $this->actingAs(createRoleTestUser());

    $this->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/index')
            ->has('roles.data', 0));

    $this->get(route('roles.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('roles/create'));

    $this->post(route('roles.store'), ['name' => 'Approver'])
        ->assertRedirect(route('roles.index'));

    $role = Role::where('name', 'Approver')->firstOrFail();

    $this->get(route('roles.show', $role))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/view')
            ->where('role.name', 'Approver')
            ->where('role.users_count', 0)
            ->where('role.module_approvers_count', 0));

    $this->get(route('roles.edit', $role))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/edit')
            ->where('role.name', 'Approver'));

    $this->put(route('roles.update', $role), ['name' => 'Senior Approver'])
        ->assertRedirect(route('roles.index'));

    $this->assertDatabaseHas('roles', [
        'id' => $role->id,
        'name' => 'Senior Approver',
    ]);

    $this->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

test('a role assigned to a module approver cannot be deleted', function () {
    $actingUser = createRoleTestUser();
    $this->actingAs($actingUser);

    $role = Role::create(['name' => 'Module Approver']);
    $moduleId = DB::table('access_module_configs')->insertGetId([
        'module_name' => 'Role Test Module',
        'max_approvers' => 1,
        'required_approvals' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('module_approvers')->insert([
        'module_id' => $moduleId,
        'user_id' => null,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->delete(route('roles.destroy', $role))
        ->assertSessionHasErrors('role');

    $this->assertDatabaseHas('roles', ['id' => $role->id]);
    $this->assertDatabaseHas('module_approvers', ['role_id' => $role->id]);
});

test('a role assigned to a user cannot be deleted', function () {
    $actingUser = createRoleTestUser();
    $role = Role::create(['name' => 'Assigned Role']);
    $actingUser->roles()->attach($role);

    $this->actingAs($actingUser)
        ->delete(route('roles.destroy', $role))
        ->assertSessionHasErrors('role');

    $this->assertDatabaseHas('roles', ['id' => $role->id]);
    $this->assertDatabaseHas('user_has_roles', [
        'user_id' => $actingUser->id,
        'role_id' => $role->id,
    ]);
});

test('role form request rejects duplicate names and permits retaining the current name', function () {
    $this->actingAs(createRoleTestUser());
    $existingRole = Role::create(['name' => 'Existing Role']);
    $roleToUpdate = Role::create(['name' => 'Role To Update']);

    $this->from(route('roles.create'))
        ->post(route('roles.store'), ['name' => 'Existing Role'])
        ->assertSessionHasErrors('name');

    $this->put(route('roles.update', $roleToUpdate), ['name' => 'Existing Role'])
        ->assertSessionHasErrors('name');

    $this->put(route('roles.update', $roleToUpdate), ['name' => 'Role To Update'])
        ->assertRedirect(route('roles.index'));

    $this->assertDatabaseHas('roles', ['id' => $existingRole->id, 'name' => 'Existing Role']);
    $this->assertDatabaseHas('roles', ['id' => $roleToUpdate->id, 'name' => 'Role To Update']);
});
