<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeGroupAllowedMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'method',
        'is_allowed',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(EmployeeGroup::class, 'group_id');
    }
}






