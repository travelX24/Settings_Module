<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceExceptionalDay extends Model
{
    protected $table = 'attendance_exceptional_days';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'period_type',
        'start_date',
        'end_date',
        'apply_on',
        'absence_multiplier',
        'late_multiplier',
        'grace_hours',
        'scope_type',
        'include',
        'exclude',
        'notify_policy',
        'notify_message',
        'notified_at',
        'retroactive',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'include' => 'array',
        'exclude' => 'array',
        'is_active' => 'boolean',
        'notified_at' => 'datetime',
        'absence_multiplier' => 'decimal:2',
        'late_multiplier' => 'decimal:2',
    ];

    public function getTimelineStatusAttribute(): string
    {
        $today = now()->toDateString();

        if ($this->end_date->toDateString() < $today) return 'ended';
        if ($this->start_date->toDateString() > $today) return 'upcoming';
        return 'current';
    }
}
