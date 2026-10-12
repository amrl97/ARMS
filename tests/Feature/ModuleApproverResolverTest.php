<?php

use App\Models\Department;
use App\Models\ModuleApprover;
use App\Models\Role;
use App\Models\User;
use App\Services\ModuleApproverResolver;

test('direct-user approver rules resolve only active users without restricted roles', function () {
    $department = Department::create(['name' => 'Resolver Direct Users']);
    $adminRole = Role::create(['name' => 'Admin']);
    $staff = User::factory()->create(['department_id' => $department->id]);
    $inactive = User::factory()->create([
        'department_id' => $department->id,
        'is_active' => false,
    ]);
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->roles()->attach($adminRole);

    $resolver = new ModuleApproverResolver();

    $staffRule = new ModuleApprover(['user_id' => $staff->id]);
    $inactiveRule = new ModuleApprover(['user_id' => $inactive->id]);
    $adminRule = new ModuleApprover(['user_id' => $admin->id]);

    expect($resolver->resolve($staffRule)->modelKeys())->toBe([$staff->id])
        ->and($resolver->resolve($inactiveRule))->toBeEmpty()
        ->and($resolver->resolve($adminRule))->toBeEmpty();
});

test('role approver rules resolve active eligible users in the optional department scope', function () {
    $finance = Department::create(['name' => 'Resolver Finance']);
    $operations = Department::create(['name' => 'Resolver Operations']);
    $role = Role::create(['name' => 'Resolver Approver']);
    $adminRole = Role::create(['name' => 'Admin']);
    $superAdminRole = Role::create(['name' => 'Super Admin']);

    $financeUser = User::factory()->create(['department_id' => $finance->id]);
    $financeUser->roles()->attach($role);

    $operationsUser = User::factory()->create(['department_id' => $operations->id]);
    $operationsUser->roles()->attach($role);

    $inactiveUser = User::factory()->create([
        'department_id' => $finance->id,
        'is_active' => false,
    ]);
    $inactiveUser->roles()->attach($role);

    $adminUser = User::factory()->create(['department_id' => $finance->id]);
    $adminUser->roles()->attach([$role->id, $adminRole->id]);

    $superAdminUser = User::factory()->create(['department_id' => $finance->id]);
    $superAdminUser->roles()->attach([$role->id, $superAdminRole->id]);

    $resolver = new ModuleApproverResolver();
    $scopedRule = new ModuleApprover([
        'role_id' => $role->id,
        'department_id' => $finance->id,
    ]);
    $unscopedRule = new ModuleApprover(['role_id' => $role->id]);

    expect($resolver->resolve($scopedRule)->modelKeys())->toBe([$financeUser->id])
        ->and($resolver->resolve($unscopedRule)->modelKeys())
        ->toContain($financeUser->id, $operationsUser->id)
        ->not->toContain($inactiveUser->id, $adminUser->id, $superAdminUser->id);
});

test('malformed module approver rules cannot be resolved', function () {
    $resolver = new ModuleApproverResolver();

    expect(fn () => $resolver->resolve(new ModuleApprover()))
        ->toThrow(\InvalidArgumentException::class)
        ->and(fn () => $resolver->resolve(new ModuleApprover([
            'user_id' => 1,
            'role_id' => 1,
        ])))
        ->toThrow(\InvalidArgumentException::class)
        ->and(fn () => $resolver->resolve(new ModuleApprover([
            'user_id' => 1,
            'department_id' => 1,
        ])))
        ->toThrow(\InvalidArgumentException::class);
});
