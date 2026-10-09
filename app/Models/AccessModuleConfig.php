<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccessModuleConfig extends Model
{
    protected $fillable = [
        'module_name',
        'max_approvers',
        'required_approvals',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_approvers' => 'integer',
            'required_approvals' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeAvailableForRequests(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function moduleApprovers()
    {
        return $this->hasMany(ModuleApprover::class, 'module_id');
    }
}
