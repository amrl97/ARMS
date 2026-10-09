<?php

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;

test('the database seeder creates required roles and assigns the default account roles', function () {
    $this->seed();

    expect(Role::query()->pluck('name')->all())
        ->toContain('Super Admin', 'Admin', 'Head of Department', 'Staff', 'Senior Executive');

    $this->assertDatabaseHas('users', [
        'fullname' => 'UAMS Administrator',
        'email' => 'uams@perdana.my',
        'is_active' => true,
    ]);
    $this->assertDatabaseHas('users', [
        'fullname' => 'IT Support',
        'email' => 'itsupport@perdana.my',
        'is_active' => true,
    ]);

    $administrator = User::query()->with('roles')->where('email', 'uams@perdana.my')->firstOrFail();
    expect($administrator->roles->pluck('name')->all())->toBe(['Super Admin']);

    $itSupport = User::query()->with('roles')->where('email', 'itsupport@perdana.my')->firstOrFail();
    expect($itSupport->roles->pluck('name')->all())->toBe(['Admin']);

    $this->seed();

    $this->assertDatabaseCount('users', 79);
    expect(User::query()->with('roles')->where('email', 'uams@perdana.my')->firstOrFail()->roles->pluck('name')->all())
        ->toBe(['Super Admin']);
    expect(User::query()->with('roles')->where('email', 'itsupport@perdana.my')->firstOrFail()->roles->pluck('name')->all())
        ->toBe(['Admin']);
});

test('the database seeder rolls back a default account when role assignment fails', function () {
    $roleAssignmentService = Mockery::mock(RoleAssignmentService::class);
    $roleAssignmentService
        ->shouldReceive('assignRoles')
        ->once()
        ->andThrow(new RuntimeException('Role assignment failed.'));
    $this->app->instance(RoleAssignmentService::class, $roleAssignmentService);

    expect(fn () => $this->seed())->toThrow(RuntimeException::class, 'Role assignment failed.');

    $this->assertDatabaseMissing('users', ['email' => 'uams@perdana.my']);
});
