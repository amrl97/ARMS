<?php

namespace App\Services;

use App\Enums\AccessRequestDetailStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalStep;
use App\Models\AccessRequestApproval;
use App\Models\AccessRequestDetail;
use App\Models\User;
use App\Policies\AccessRequestApprovalPolicy;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessRequestApprovalWorkflowService
{
    public function decide(
        User $approver,
        AccessRequestApproval $approval,
        ApprovalStatus $decision,
        ?string $rejectionReason = null,
    ): AccessRequestApproval {
        if (! in_array($decision, [ApprovalStatus::Approved, ApprovalStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Choose approve or reject for an approval decision.',
            ]);
        }

        if ($decision === ApprovalStatus::Rejected && blank($rejectionReason)) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A reason is required when rejecting an access request.',
            ]);
        }

        return DB::transaction(function () use ($approver, $approval, $decision, $rejectionReason): AccessRequestApproval {
            $detail = AccessRequestDetail::query()
                ->with('request')
                ->whereKey($approval->request_detail_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedApproval = AccessRequestApproval::query()
                ->whereKey($approval->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                app(AccessRequestApprovalPolicy::class)->decide($approver, $lockedApproval),
                403,
            );

            $lockedApproval->forceFill([
                'status' => $decision->value,
                'rejection_reason' => $decision === ApprovalStatus::Rejected
                    ? trim((string) $rejectionReason)
                    : null,
            ])->save();

            if ($decision === ApprovalStatus::Rejected) {
                $detail->approvals()
                    ->where('status', ApprovalStatus::Pending->value)
                    ->update(['status' => ApprovalStatus::Skipped->value]);

                $detail->forceFill([
                    'status' => AccessRequestDetailStatus::Rejected->value,
                ])->save();
            } elseif ($lockedApproval->step === ApprovalStep::Module->value) {
                $detail->approvals()
                    ->where('step', ApprovalStep::Module->value)
                    ->where('status', ApprovalStatus::Pending->value)
                    ->update(['status' => ApprovalStatus::Skipped->value]);

                $accessRequest = $detail->request;
                $excludedUserIds = [
                    $accessRequest->requested_by,
                    $accessRequest->internal_beneficiary,
                ];
                $finalApproverIds = DB::table('users')
                    ->join('user_has_roles', 'users.id', '=', 'user_has_roles.user_id')
                    ->join('roles', 'user_has_roles.role_id', '=', 'roles.id')
                    ->leftJoin('access_request_approvals as existing_approvals', static function (JoinClause $join) use ($detail): void {
                        $join->on('existing_approvals.approver_id', '=', 'users.id')
                            ->where('existing_approvals.request_detail_id', '=', $detail->getKey());
                    })
                    ->where('users.is_active', true)
                    ->where('roles.name', 'Admin')
                    ->whereNotIn('users.id', array_values(array_filter($excludedUserIds)))
                    ->whereNull('existing_approvals.id')
                    ->orderBy('users.id')
                    ->lockForUpdate()
                    ->pluck('users.id');

                if ($finalApproverIds->isEmpty()) {
                    throw ValidationException::withMessages([
                        'decision' => 'No eligible active Admin is available for final approval. Please contact an administrator.',
                    ]);
                }

                $detail->approvals()->createMany(
                    $finalApproverIds->map(static fn (int|string $finalApproverId): array => [
                        'step' => ApprovalStep::Final->value,
                        'approver_id' => $finalApproverId,
                        'status' => ApprovalStatus::Pending->value,
                    ])->all(),
                );
            } elseif ($lockedApproval->step === ApprovalStep::Final->value) {
                $detail->approvals()
                    ->where('step', ApprovalStep::Final->value)
                    ->where('status', ApprovalStatus::Pending->value)
                    ->update(['status' => ApprovalStatus::Skipped->value]);

                $detail->forceFill([
                    'status' => AccessRequestDetailStatus::Approved->value,
                ])->save();
            }

            return $lockedApproval;
        });
    }
}
