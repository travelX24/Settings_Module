<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceGraceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'late_grace_minutes',
        'early_leave_grace_minutes',
        'auto_checkout_after_minutes',
        'is_global_default',
        'saas_company_id',
    ];

    protected $casts = [
        'late_grace_minutes' => 'integer',
        'early_leave_grace_minutes' => 'integer',
        'auto_checkout_after_minutes' => 'integer',
        'is_global_default' => 'boolean',
    ];

    /**
     * Scopes
     */
    public function scopeGlobalDefault($query)
    {
        return $query->where('is_global_default', true);
    }

    /**
     * Get or create global default grace settings
     */
    public static function getGlobalDefault()
    {
        return static::globalDefault()->first() ?? static::create([
            'late_grace_minutes' => 15,
            'early_leave_grace_minutes' => 10,
            'auto_checkout_after_minutes' => 120,
            'is_global_default' => true,
        ]);
    }
}






