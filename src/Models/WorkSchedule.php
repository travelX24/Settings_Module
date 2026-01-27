<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class WorkSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'week_start_day',
        'week_end_day',
        'work_days',
        'is_default',
        'is_active',
        'saas_company_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'work_days' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(WorkSchedulePeriod::class)->orderBy('sort_order');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(WorkScheduleException::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}






