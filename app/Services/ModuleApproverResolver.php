<?php

namespace App\Services;

use App\Models\AccessModuleConfig;
use App\Models\ModuleApprover;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ModuleApproverResolver
{
    /**
     * Resolve module rules for a new request, excluding the requester and internal beneficiary.
     */
    public function resolveForSubmission(
        AccessModuleConfig $module,
        User $requester,
        User|string|null $beneficiary,
    ): ModuleApproverResolution {

        // 1. If the module has no approvers, we can skip the step entirely
        if ($module->max_approvers === 0) {
            return new ModuleApproverResolution(
                approvers: new Collection,
                hasEligibleApproversBeforeExclusions: false,
                skipModuleStep: true,
            );
        }

        // 2. Get all eligible approvers for the module, before exclusions
        $eligibleApprovers = $module->moduleApprovers
            ->flatMap(fn (ModuleApprover $rule): Collection => $this->resolve($rule))
            ->unique('id')
            ->values();

        // 3. Remove the requester and internal beneficiary from the list of approvers
        $excludedUserIds = [$requester->getKey()];
        if ($beneficiary instanceof User) {
            $excludedUserIds[] = $beneficiary->getKey();
        }

        $approvers = $eligibleApprovers
            ->reject(fn (User $user): bool => in_array($user->getKey(), $excludedUserIds, true))
            ->values();
        $approvers = $requester->newCollection($approvers->all());

        // 4. Determine if we have eligible approvers before exclusions and if we can skip the module step
        $hasEligibleApproversBeforeExclusions = $eligibleApprovers->isNotEmpty();
        $skipModuleStep = $hasEligibleApproversBeforeExclusions && $approvers->isEmpty();

        return new ModuleApproverResolution(
            approvers: $approvers,
            hasEligibleApproversBeforeExclusions: $hasEligibleApproversBeforeExclusions,
            skipModuleStep: $skipModuleStep,
        );
    }

    /**
     * Resolve a direct-user or role/department rule to active, eligible users.
     *
     * @return Collection<int, User>
     */
    public function resolve(ModuleApprover $rule): Collection
    {
        $hasUser = $rule->user_id !== null;
        $hasRole = $rule->role_id !== null;

        if ($hasUser === $hasRole || ($hasUser && $rule->department_id !== null)) {
            throw new InvalidArgumentException('A module approver rule must be either a user or a role with an optional department.');
        }

        $users = User::query()
            ->where('is_active', true)
            ->whereDoesntHave('roles', fn (Builder $roles): Builder => $roles->whereIn(
                'roles.name',
                User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES,
            ));

        if ($hasUser) {
            return $users
                ->whereKey($rule->user_id)
                ->get();
        }

        return $users
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->whereKey($rule->role_id)
                ->whereNotIn('roles.name', User::RESTRICTED_INTERNAL_BENEFICIARY_ROLES))
            ->when(
                $rule->department_id !== null,
                fn (Builder $query): Builder => $query->where('department_id', $rule->department_id),
            )
            ->get();
    }
}
