<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function request()
    {
        return $this->belongsTo(AccessRequest::class, 'request_id');
    }

    public function module()
    {
        return $this->belongsTo(AccessModuleConfig::class, 'module_id');
    }

    public function approvals()
    {
        return $this->hasMany(AccessRequestApproval::class, 'request_detail_id');
    }
}
