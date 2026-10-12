<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccessRequestDetail extends Model
{
    protected static function booted(): void
    {
        static::saved(function (self $detail): void {
            if ($detail->wasRecentlyCreated || $detail->wasChanged('status')) {
                $detail->request()->firstOrFail()->recalculateStatus();
            }
        });
    }

    protected $fillable = [
        'module_id',
        'access_type',
        'priority',
        'justification',
        'status',
        'start_date',
        'end_date',
    ];

    /**
     * @return BelongsTo<AccessRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(AccessRequest::class, 'request_id');
    }

    /**
     * @return BelongsTo<AccessModuleConfig, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(AccessModuleConfig::class, 'module_id');
    }

    /**
     * @return HasMany<AccessRequestApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(AccessRequestApproval::class, 'request_detail_id');
    }
}
