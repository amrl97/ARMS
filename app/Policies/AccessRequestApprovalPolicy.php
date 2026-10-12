<?php

namespace App\Policies;

use App\Enums\AccessRequestDetailStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalStep;
use App\Models\AccessRequestApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessRequestApprovalPolicy
{
    public function decide(User $user, AccessRequestApproval $approval): bool
    {
        return $approval->newQuery()
            ->whereKey($approval->getKey())
            ->where('approver_id', $user->getKey())
            ->where('status', ApprovalStatus::Pending->value)
            ->whereIn('step', array_map(
                static fn (ApprovalStep $step): string => $step->value,
                ApprovalStep::cases(),
            ))
            ->whereHas('requestDetail', static function (Builder $details) use ($user): void {
                $details
                    ->where('status', AccessRequestDetailStatus::Pending->value)
                    ->whereHas('request', static function (Builder $requests) use ($user): void {
                        $requests
                            ->where('requested_by', '!=', $user->getKey())
                            ->where(static function (Builder $beneficiaries) use ($user): void {
                                $beneficiaries
                                    ->whereNull('internal_beneficiary')
                                    ->orWhere('internal_beneficiary', '!=', $user->getKey());
                            });
                    });
            })
            ->exists();
    }
}
