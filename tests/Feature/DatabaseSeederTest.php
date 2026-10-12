<?php

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;

test('the database seeder creates required roles and assigns the default account roles', function () {
    $this->seed();

    expect(Role::query()->pluck('name')->all())
        ->toContain('Super Admin', 'Admin', 'General Manager', 'Head of Department', 'Staff', 'Senior Executive');

    $leadershipAssignments = [
        'ibrahim.lakkoni@perdana.my' => 'Head of Department',
        'janice.teh@perdana.my' => 'Head of Department',
        'abdul.ghafur@perdana.my' => 'Head of Department',
        'mazlan.mokhtar@perdana.my' => 'Head of Department',
        'fahim.rosley@perdana.my' => 'Head of Department',
        'khairul.anwar@perdana.my' => 'Head of Department',
        'kevin.ng@perdana.my' => 'Head of Department',
        'marina@perdana.my' => 'Head of Department',
        'azlan@perdana.my' => 'Head of Department',
        'nik.nurin@perdana.my' => 'Head of Department',
        'gs.tiu@perdana.my' => 'Head of Department',
        'rajen.k@perdana.my' => 'General Manager',
        'adyhafis.razab@perdana.my' => 'General Manager',
    ];

    foreach ($leadershipAssignments as $email => $roleName) {
        $user = User::query()->with('roles')->where('email', $email)->firstOrFail();
        expect($user->roles->pluck('name')->all())->toBe([$roleName]);
    }

    $regularUser = User::query()->with('roles')->where('email', 'harira.malek@perdana.my')->firstOrFail();
    expect($regularUser->roles->pluck('name')->all())->toBe(['Staff']);

    expect(User::query()->whereHas('roles', fn ($query) => $query->where('name', 'Staff'))->count())
        ->toBe(64);

    $padmarajen = User::query()->where('email', 'rajen.k@perdana.my')->firstOrFail();
    expect($padmarajen->department->name)->toBe('Fleet Management');

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
        ->andReturnUsing(function (User $user): void {
            if ($user->email === 'uams@perdana.my') {
                throw new RuntimeException('Role assignment failed.');
            }
        });
    $this->app->instance(RoleAssignmentService::class, $roleAssignmentService);

    expect(fn () => $this->seed())->toThrow(RuntimeException::class, 'Role assignment failed.');

    $this->assertDatabaseMissing('users', ['email' => 'uams@perdana.my']);
});
