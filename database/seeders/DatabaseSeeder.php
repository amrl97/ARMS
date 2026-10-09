<?php

namespace Database\Seeders;

use App\Models\AccessModuleConfig;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $Department = 
        Department::firstOrCreate(['name' => 'Information Technology']);
        Department::firstOrCreate(['name' => 'Human Resources and Administration']);
        Department::firstOrCreate(['name' => 'Finance']);
        Department::firstOrCreate(['name' => 'Technical']);
        Department::firstOrCreate(['name' => 'Operations']);
        Department::firstOrCreate(['name' => 'Health, Safety and Environment']);
        Department::firstOrCreate(['name' => 'Crewing']);
        Department::firstOrCreate(['name' => 'Corporate Affairs']);
        Department::firstOrCreate(['name' => 'Procurement']);
        Department::firstOrCreate(['name' => 'MD\'s Office']);
        Department::firstOrCreate(['name' => 'Chartering']);

        $this->call(StaffUsersSeeder::class);

        collect([
            'Super Admin',
            'Admin',
            'Head of Department',
            'Staff',
            'Senior Executive',
        ])->each(fn (string $name) => Role::firstOrCreate(['name' => $name]));

        AccessModuleConfig::firstOrCreate(['module_name' => 'Sangfor EDR Portal']);
        AccessModuleConfig::firstOrCreate(
            ['module_name' => 'Sharepoint Finance'],
            ['max_approvers' => 2, 'required_approvals' => 1],
        );
        AccessModuleConfig::firstOrCreate(
            ['module_name' => 'SavvyMariner'],
            ['max_approvers' => 1, 'required_approvals' => 1],
        );

        $defaultAccounts = [
            [
                'fullname' => 'UAMS Administrator',
                'email' => 'uams@perdana.my',
                'role' => 'Super Admin',
            ],
            [
                'fullname' => 'IT Support',
                'email' => 'itsupport@perdana.my',
                'role' => 'Admin',
            ],
        ];

        foreach ($defaultAccounts as $account) {
            DB::transaction(function () use ($account, $Department): void {
                $user = User::firstOrCreate(
                    ['email' => $account['email']],
                    [
                        'fullname' => $account['fullname'],
                        'department_id' => $Department->id,
                        'is_active' => true,
                        'email_verified_at' => now(),
                        'password' => Hash::make('Perdana@2026'),
                    ],
                );

                $role = Role::query()->where('name', $account['role'])->firstOrFail();
                app(RoleAssignmentService::class)->assignRoles($user, [$role->id]);
            });
        }
    }
}
