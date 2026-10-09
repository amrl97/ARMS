<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleAssignmentService
{
    /**
     * @param  array<int, int|string>  $roleIds
     */
    public function assignRoles(User $user, array $roleIds): void
    {
        DB::transaction(function () use ($user, $roleIds): void {
            $this->syncRoles($user, $roleIds);
        });
    }

    /**
     * Update department/activity fields and roles as one HOD-checked operation.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $roleIds
     */
    public function updateUser(User $user, array $attributes, array $roleIds): void
    {
        DB::transaction(function () use ($user, $attributes, $roleIds): void {
            $updatedUser = clone $user;
            $updatedUser->fill($attributes);

            $this->validateHeadOfDepartmentAssignment($updatedUser, $roleIds);
            $updatedUser->save();
            $updatedUser->roles()->sync($roleIds);
        });

        $user->refresh();
    }

    /**
     * @param  array<int, int|string>  $roleIds
     */
    private function syncRoles(User $user, array $roleIds): void
    {
        $this->validateHeadOfDepartmentAssignment($user, $roleIds);
        $user->roles()->sync($roleIds);
    }

    /**
     * @param  array<int, int|string>  $roleIds
     */
    private function validateHeadOfDepartmentAssignment(User $user, array $roleIds): void
    {
        Department::query()
            ->whereKey($user->department_id)
            ->lockForUpdate()
            ->firstOrFail();

        $headOfDepartmentRoleId = Role::query()
            ->where('name', 'Head of Department')
            ->value('id');

        $assignsHeadOfDepartment = $headOfDepartmentRoleId !== null
            && in_array((string) $headOfDepartmentRoleId, array_map('strval', $roleIds), true);

        if ($user->is_active && $assignsHeadOfDepartment) {
            $existingHead = User::query()
                ->where('department_id', $user->department_id)
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('roles.id', $headOfDepartmentRoleId))
                ->whereKeyNot($user->getKey())
                ->exists();

            if ($existingHead) {
                throw ValidationException::withMessages([
                    'role_ids' => 'This department already has an active Head of Department.',
                ]);
            }
        }
    }
}
