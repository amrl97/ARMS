<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessRequestComment extends Model
{
    protected $fillable = [
        'comment'
    ];

    public function request()
    {
        return $this->belongsTo(AccessRequest::class, 'request_id');
    }

    public function commentedBy()
    {
        return $this->belongsTo(User::class, 'commented_by');
    }
}
