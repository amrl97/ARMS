<?php

namespace App\Observers;

use App\Models\User;
use App\Services\BeneficiaryDeactivationService;

class UserObserver
{
    public function updated(User $user): void
    {
        if ($user->wasChanged('is_active') && ! $user->is_active) {
            app(BeneficiaryDeactivationService::class)->handle($user);
        }
    }
}
