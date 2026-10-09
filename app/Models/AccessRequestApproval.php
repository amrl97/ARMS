<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessRequestApproval extends Model
{
    protected $fillable = [
        'request_detail_id',
        'step',
        'approver_id',
        'status',
        'rejection_reason',
    ];

    public function requestDetail()
    {
        return $this->belongsTo(AccessRequestDetail::class, 'request_detail_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
