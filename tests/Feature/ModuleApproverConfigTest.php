<?php

use App\Models\AccessModuleConfig;
use App\Models\Department;
use App\Models\ModuleApprover;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('module configuration provides approver choices and warns for unresolved rules', function () {
    $department = Department::create(['name' => 'Finance']);
    $otherDepartment = Department::create(['name' => 'Operations']);
    $staffRole = Role::create(['name' => 'Staff']);
    $financeRole = Role::create(['name' => 'Finance Approver']);
    $adminRole = Role::create(['name' => 'Admin']);
    $superAdminRole = Role::create(['name' => 'Super Admin']);
    $viewer = User::factory()->create(['department_id' => $department->id]);
    $activeApprover = User::factory()->create([
        'fullname' => 'Active Approver',
        'department_id' => $department->id,
    ]);
    $activeApprover->roles()->attach([$financeRole->id, $staffRole->id]);

    $inactiveApprover = User::factory()->create([
        'fullname' => 'Inactive Approver',
        'department_id' => $department->id,
        'is_active' => false,
    ]);

    $restrictedApprover = User::factory()->create([
        'fullname' => 'Restricted Approver',
        'department_id' => $department->id,
    ]);
    $restrictedApprover->roles()->attach([$staffRole->id, $adminRole->id]);

    $module = AccessModuleConfig::create([
        'module_name' => 'Finance System',
        'max_approvers' => 3,
        'required_approvals' => 1,
        'is_active' => true,
    ]);

    ModuleApprover::create([
        'module_id' => $module->id,
        'user_id' => $inactiveApprover->id,
    ]);
    ModuleApprover::create([
        'module_id' => $module->id,
        'role_id' => $financeRole->id,
        'department_id' => $otherDepartment->id,
    ]);
    ModuleApprover::create([
        'module_id' => $module->id,
        'role_id' => $staffRole->id,
    ]);

    $this->actingAs($viewer)
        ->get("/admin/configs/{$module->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Configs/edit')
            ->where('config.module_approvers.0.resolved_user_count', 0)
            ->where('config.module_approvers.1.resolved_user_count', 0)
            ->where('config.module_approvers.2.resolved_user_count', 1)
            ->where('approverUsers', fn ($users) => $users->contains('fullname', 'Active Approver')
                && ! $users->contains('fullname', 'Restricted Approver'))
            ->where('approverRoles', fn ($roles) => $roles->contains('name', 'Staff')
                && ! $roles->contains('name', 'Admin')
                && ! $roles->contains('name', 'Super Admin')));
});

test('module approver rules can be added and removed from the configuration screen', function () {
    $department = Department::create(['name' => 'Approver Configuration']);
    $actor = User::factory()->create(['department_id' => $department->id]);
    $approver = User::factory()->create([
        'fullname' => 'Configured Approver',
        'department_id' => $department->id,
    ]);
    $role = Role::create(['name' => 'System Owner']);
    $module = AccessModuleConfig::create([
        'module_name' => 'System Configuration',
        'max_approvers' => 2,
        'required_approvals' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($actor)
        ->post('/admin/configs/approvers', [
            'module_id' => $module->id,
            'role_id' => $role->id,
            'department_id' => $department->id,
        ])
        ->assertRedirect("/admin/configs/{$module->id}/edit")
        ->assertSessionHas('inertia.flash_data.toast.message', 'Module approver rule added successfully.');

    $rule = ModuleApprover::query()->sole();
    expect($rule->role_id)->toBe($role->id)
        ->and($rule->department_id)->toBe($department->id);

    $this->post('/admin/configs/approvers', [
        'module_id' => $module->id,
        'user_id' => $approver->id,
    ])->assertRedirect("/admin/configs/{$module->id}/edit");

    $directUserRule = ModuleApprover::query()->where('user_id', $approver->id)->sole();

    $this->delete("/admin/configs/approvers/{$directUserRule->id}")
        ->assertRedirect("/admin/configs/{$module->id}/edit")
        ->assertSessionHas('inertia.flash_data.toast.message', 'Module approver rule removed successfully.');

    $this->assertDatabaseMissing('module_approvers', ['id' => $directUserRule->id]);
    $this->assertDatabaseHas('module_approvers', ['id' => $rule->id]);
});
