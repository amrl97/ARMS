<?php

namespace App\Policies;

use App\Models\User;

class AccessRequestPolicy
{
    public function create(User $user): bool
    {
        return ! $user->hasRole('Admin');
    }

    public function selectInternalBeneficiary(User $user, User $beneficiary): bool
    {
        return ! $user->hasRole('Admin')
            && $beneficiary->isEligibleInternalBeneficiary();
    }
}
