<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ModuleApproverResolution
{
    /**
     * @param  Collection<int, User>  $approvers
     */
    public function __construct(

        // The list of approvers after exclusions
        // $approvers: Users who can approve after the requester and beneficiary were removed
        public readonly Collection $approvers,

        // Indicates whether there were any eligible approvers before exclusions
        // $hasEligibleApproversBeforeExclusions: True if there were eligible approvers before removing the requester and beneficiary
        public readonly bool $hasEligibleApproversBeforeExclusions,

        // Indicates whether the module step can be skipped 
         // $skipModuleStep: True if the module step can be skipped (i.e., there were eligible approvers before exclusions, but none after exclusions)
        public readonly bool $skipModuleStep,
    ) {}

    public function requiresConfiguration(): bool
    {
        return ! $this->skipModuleStep && ! $this->hasEligibleApproversBeforeExclusions;
    }
}
