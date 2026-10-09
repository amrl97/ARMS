<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleApprover extends Model
{
    protected $fillable = [
        'module_id',
        'user_id',
        'role_id',
        'department_id',
    ];

    public function module()
    {
        return $this->belongsTo(AccessModuleConfig::class, 'module_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
