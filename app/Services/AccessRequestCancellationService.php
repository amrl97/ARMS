<?php

namespace App\Services;

use App\Enums\AccessRequestDetailStatus;
use App\Enums\ApprovalStatus;
use App\Models\AccessRequest;
use App\Models\AccessRequestDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessRequestCancellationService
{
    public function cancel(User $requester, AccessRequest $request): int
    {
        if ((int) $request->requested_by !== (int) $requester->getKey()) {
            throw ValidationException::withMessages([
                'request' => 'Only the original requester can cancel a request.',
            ]);
        }

        $pendingDetails = $request->details()
            ->where('status', AccessRequestDetailStatus::Pending->value)
            ->get();

        if ($pendingDetails->isEmpty()) {
            throw ValidationException::withMessages([
                'request' => 'This request has no pending lines to cancel.',
            ]);
        }

        return DB::transaction(function () use ($pendingDetails): int {
            $cancelled = 0;

            foreach ($pendingDetails as $detail) {
                /** @var AccessRequestDetail $detail */
                $detail->approvals()
                    ->where('status', ApprovalStatus::Pending->value)
                    ->update(['status' => ApprovalStatus::Skipped->value]);

                $detail->forceFill([
                    'status' => AccessRequestDetailStatus::Cancelled->value,
                ])->save();

                $cancelled++;
            }

            return $cancelled;
        });
    }
}
