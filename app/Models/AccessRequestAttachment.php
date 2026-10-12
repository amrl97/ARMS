<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessRequestAttachment extends Model
{
    protected $fillable = [
        'file_name',
        'file_path',
        'file_size',
    ];

    /**
     * @return BelongsTo<AccessRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(AccessRequest::class, 'request_id');
    }
}
