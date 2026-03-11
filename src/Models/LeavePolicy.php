<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeavePolicy extends Model
{
    protected $table = 'leave_policies';

    protected $fillable = [
        'company_id',
        'policy_year_id',

        'name',
        'leave_type',
        'days_per_year',

        'gender', // all|male|female
        'is_active',
        'show_in_app',
        'requires_attachment',

        'description',
        'settings', // json for advanced rules
        'excluded_contract_types',
    ];

    protected $casts = [
        'days_per_year' => 'decimal:2',
        'is_active' => 'boolean',
        'show_in_app' => 'boolean',
        'requires_attachment' => 'boolean',
        'settings' => 'array',
        'excluded_contract_types' => 'array',
    ];

    public function year(): BelongsTo
    {
        return $this->belongsTo(LeavePolicyYear::class, 'policy_year_id');
    }
}
