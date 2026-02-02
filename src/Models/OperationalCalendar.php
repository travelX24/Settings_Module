<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalCalendar extends Model
{
    protected $table = 'operational_calendars';

    protected $fillable = [
        'company_id',
        'calendar_type',
    ];
}
