<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedulePeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_schedule_id',
        'start_time',
        'end_time',
        'is_night_shift',
        'sort_order',
    ];

    protected $casts = [
        'is_night_shift' => 'boolean',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }
}






