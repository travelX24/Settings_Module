<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeavePolicyYear extends Model
{
    protected $table = 'leave_policy_years';

    protected $fillable = [
        'company_id',
        'year',
        'starts_on',
        'ends_on',
        'is_active',
    ];

    protected $casts = [
        'year' => 'integer',
        'is_active' => 'boolean',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function leavePolicies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class, 'policy_year_id');
    }
}
