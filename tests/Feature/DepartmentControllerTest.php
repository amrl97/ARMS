<?php

use App\Models\Department;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createDepartmentCrudUser(): User
{
    $department = Department::create(['name' => 'Department Test']);

    return User::factory()->create([
        'department_id' => $department->id,
    ]);
}

test('an authenticated user can manage departments', function () {
    $this->actingAs(createDepartmentCrudUser());

    $this->get(route('departments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('departments/index')
            ->has('departments.data', 1)
            ->where('departments.data.0.users_count', 1));

    $this->get(route('departments.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('departments/create'));

    $this->post(route('departments.store'), ['name' => 'Finance'])
        ->assertRedirect(route('departments.index'));

    $department = Department::where('name', 'Finance')->firstOrFail();

    $this->get(route('departments.show', $department))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('departments/view')
            ->where('department.name', 'Finance'));

    $this->get(route('departments.edit', $department))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('departments/edit')
            ->where('department.name', 'Finance'));

    $this->put(route('departments.update', $department), ['name' => 'Accounting'])
        ->assertRedirect(route('departments.index'));

    $this->assertDatabaseHas('departments', [
        'id' => $department->id,
        'name' => 'Accounting',
    ]);

    $this->delete(route('departments.destroy', $department))
        ->assertRedirect(route('departments.index'));

    $this->assertDatabaseMissing('departments', ['id' => $department->id]);
});

test('a department with users cannot be deleted', function () {
    $user = createDepartmentCrudUser();

    $this->actingAs($user)
        ->delete(route('departments.destroy', $user->department))
        ->assertSessionHasErrors('department');

    $this->assertDatabaseHas('departments', [
        'id' => $user->department_id,
    ]);
});
