<?php

namespace App\Services;

use App\Enums\AccessRequestDetailStatus;
use App\Enums\ApprovalStatus;
use App\Models\AccessRequestDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BeneficiaryDeactivationService
{
    public function __construct(
        private readonly DeactivatedApproverReplacementService $approverReplacementService,
    ) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $beneficiaryDetails = AccessRequestDetail::query()
                ->whereHas('request', static function ($requests) use ($user): void {
                    $requests->where(static function ($beneficiaries) use ($user): void {
                        $beneficiaries
                            ->where('internal_beneficiary', $user->getKey())
                            ->orWhere(static function ($selfRequests) use ($user): void {
                                $selfRequests
                                    ->whereNull('internal_beneficiary')
                                    ->whereNull('external_beneficiary')
                                    ->where('requested_by', $user->getKey());
                            });
                    });
                })
                ->where(static function ($details): void {
                    $details
                        ->where('status', AccessRequestDetailStatus::Pending->value)
                        ->orWhere(static function ($approvedDetails): void {
                            $approvedDetails
                                ->where('status', AccessRequestDetailStatus::Approved->value)
                                ->where(static function ($endDates): void {
                                    $endDates
                                        ->whereNull('end_date')
                                        ->orWhereDate('end_date', '>=', today()->toDateString());
                                });
                        });
                })
                ->lockForUpdate()
                ->get();

            foreach ($beneficiaryDetails as $detail) {
                if ($detail->status === AccessRequestDetailStatus::Pending->value) {
                    $detail->approvals()
                        ->where('status', ApprovalStatus::Pending->value)
                        ->update(['status' => ApprovalStatus::Skipped->value]);

                    $detail->forceFill([
                        'status' => AccessRequestDetailStatus::Cancelled->value,
                    ])->save();

                    continue;
                }

                $detail->forceFill([
                    'status' => AccessRequestDetailStatus::Revoked->value,
                ])->save();
            }

            $this->approverReplacementService->replace($user);
        });
    }
}
