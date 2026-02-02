<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialHolidayOccurrence extends Model
{
    protected $table = 'official_holiday_occurrences';

    protected $fillable = [
        'company_id',
        'template_id',
        'year_greg',
        'year_hijri',
        'start_date',
        'end_date',
        'duration_days',
        'display_hijri',
        'is_tentative',
        'is_overridden',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_tentative' => 'boolean',
        'is_overridden' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OfficialHolidayTemplate::class, 'template_id');
    }
}
