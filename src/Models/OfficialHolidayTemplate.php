<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficialHolidayTemplate extends Model
{
    protected $table = 'official_holiday_templates';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'icon',
        'calendar_type',
        'repeat_type',
        'hijri_month',
        'hijri_day',
        'greg_month',
        'greg_day',
        'once_start_date',
        'duration_days',
        'scope_type',
        'branch_ids',
        'excluded_group_ids',
        'payroll_effect',
        'overtime_policy',
        'notify_days',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'branch_ids' => 'array',
        'excluded_group_ids' => 'array',
        'once_start_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function occurrences(): HasMany
    {
        return $this->hasMany(OfficialHolidayOccurrence::class, 'template_id');
    }
}
