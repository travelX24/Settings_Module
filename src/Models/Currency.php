<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'symbol',
        'code',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];
}
