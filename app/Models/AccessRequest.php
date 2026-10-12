<?php

namespace App\Models;

use App\Enums\AccessRequestDetailStatus;
use App\Enums\AccessRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class AccessRequest extends Model
{
    protected $fillable = [
        'requested_by',
        'internal_beneficiary',
        'external_beneficiary',
        'remarks',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function internalBeneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_beneficiary');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolveBeneficiary(): User|string
    {
        if ($this->external_beneficiary !== null) {
            return $this->external_beneficiary;
        }

        $beneficiary = $this->internalBeneficiary ?? $this->requestedBy;

        if ($beneficiary === null) {
            throw new LogicException('The request beneficiary could not be resolved.');
        }

        return $beneficiary;
    }

    public function recalculateStatus(): AccessRequestStatus
    {
        $detailStatuses = $this->details()
            ->pluck('status')
            ->map(static function (string $status): AccessRequestDetailStatus {
                return AccessRequestDetailStatus::tryFrom($status)
                    ?? throw new LogicException("Unsupported access request detail status: {$status}");
            });

        if ($detailStatuses->isEmpty()) {
            throw new LogicException('Cannot calculate request status without request details.');
        }

        $activeStatuses = $detailStatuses
            ->reject(fn (AccessRequestDetailStatus $status): bool => $status === AccessRequestDetailStatus::Cancelled)
            ->values();

        if ($activeStatuses->isEmpty()) {
            $status = AccessRequestStatus::Cancelled;
        } else {
            $pendingCount = $activeStatuses->filter(
                fn (AccessRequestDetailStatus $detailStatus): bool => $detailStatus === AccessRequestDetailStatus::Pending
            )->count();
            $approvedCount = $activeStatuses->filter(
                fn (AccessRequestDetailStatus $detailStatus): bool => in_array($detailStatus, [
                    AccessRequestDetailStatus::Approved,
                    AccessRequestDetailStatus::Expired,
                    AccessRequestDetailStatus::Revoked,
                ], true)
            )->count();
            $rejectedCount = $activeStatuses->filter(
                fn (AccessRequestDetailStatus $detailStatus): bool => $detailStatus === AccessRequestDetailStatus::Rejected
            )->count();

            $status = match (true) {
                $pendingCount === $activeStatuses->count() => AccessRequestStatus::Pending,
                $pendingCount > 0 => AccessRequestStatus::InProgress,
                $rejectedCount === $activeStatuses->count() => AccessRequestStatus::Rejected,
                $approvedCount === $activeStatuses->count() => AccessRequestStatus::Approved,
                default => AccessRequestStatus::PartiallyApproved,
            };
        }

        $this->forceFill(['status' => $status->value]);

        if ($this->isDirty('status')) {
            $this->save();
        }

        return $status;
    }

    // Relationships with related models

    /**
     * @return HasMany<AccessRequestDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(AccessRequestDetail::class, 'request_id');
    }

    /**
     * @return HasMany<AccessRequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(AccessRequestAttachment::class, 'request_id');
    }

    /**
     * @return HasMany<AccessRequestComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(AccessRequestComment::class, 'request_id');
    }
}
