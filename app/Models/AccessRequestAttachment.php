<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessRequestAttachment extends Model
{
    protected $fillable = [
        //
    ];

    public function request()
    {
        return $this->belongsTo(AccessRequest::class, 'request_id');
    }
}
