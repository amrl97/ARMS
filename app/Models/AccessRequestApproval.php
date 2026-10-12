<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessRequestApproval extends Model
{
    protected $fillable = [
        'request_detail_id',
        'step',
        'approver_id',
        'status',
        'rejection_reason',
    ];

    /**
     * @return BelongsTo<AccessRequestDetail, $this>
     */
    public function requestDetail(): BelongsTo
    {
        return $this->belongsTo(AccessRequestDetail::class, 'request_detail_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
