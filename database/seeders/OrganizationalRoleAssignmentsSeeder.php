<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Database\Seeder;

class OrganizationalRoleAssignmentsSeeder extends Seeder
{
    /**
     * Seed the organizational leadership assignments.
     */
    public function run(): void
    {
        $assignments = [
            'Head of Department' => [
                'ibrahim.lakkoni@perdana.my',
                'janice.teh@perdana.my',
                'abdul.ghafur@perdana.my',
                'mazlan.mokhtar@perdana.my',
                'fahim.rosley@perdana.my',
                'khairul.anwar@perdana.my',
                'kevin.ng@perdana.my',
                'marina@perdana.my',
                'azlan@perdana.my',
                'nik.nurin@perdana.my',
                'gs.tiu@perdana.my',
            ],
            'General Manager' => [
                'rajen.k@perdana.my',
                'adyhafis.razab@perdana.my',
            ],
        ];

        $roleAssignmentService = app(RoleAssignmentService::class);

        foreach ($assignments as $roleName => $emails) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            foreach ($emails as $email) {
                $user = User::query()->where('email', $email)->firstOrFail();
                $roleAssignmentService->assignRoles($user, [$role->id]);
            }
        }
    }
}
