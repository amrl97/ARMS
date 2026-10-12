<?php

namespace App\Policies;

use App\Enums\AccessRequestDetailStatus;
use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AccessRequest $request): bool
    {
        return $this->viewableRequests($user)
            ->whereKey($request->getKey())
            ->exists();
    }

    /**
     * @return Builder<AccessRequest>
     */
    public function viewableRequests(User $user): Builder
    {
        return AccessRequest::query()->where(static function (Builder $requests) use ($user): void {
            $requests
                ->where('requested_by', $user->getKey())
                ->orWhere('internal_beneficiary', $user->getKey())
                ->orWhereHas('details.approvals', static function (Builder $approvals) use ($user): void {
                    $approvals->where('approver_id', $user->getKey());
                });
        });
    }

    public function create(User $user): bool
    {
        return ! $user->hasRole('Admin');
    }

    public function cancel(User $user, AccessRequest $request): bool
    {
        return (int) $request->requested_by === (int) $user->getKey()
            && $request->details()
                ->where('status', AccessRequestDetailStatus::Pending->value)
                ->exists();
    }

    public function selectInternalBeneficiary(User $user, User $beneficiary): bool
    {
        return ! $user->hasRole('Admin')
            && $beneficiary->isEligibleInternalBeneficiary();
    }
}
