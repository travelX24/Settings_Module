<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class UnexcusedAbsencePolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_id',
        'absence_reason_type',
        'day_selector_type',
        'day_from',
        'day_to',
        'penalty_action',
        'deduction_type',
        'deduction_value',
        'suspension_days',
        'late_minutes',
        'early_leave_minutes',
        'recurrence_count',
        'is_active',
        'is_enabled',
        'notification_message',
        'wage_unit',
        'saas_company_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'day_from' => 'integer',
        'day_to' => 'integer',
        'deduction_value' => 'decimal:2',
        'suspension_days' => 'integer',
        'late_minutes' => 'integer',
        'early_leave_minutes' => 'integer',
        'recurrence_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicy::class, 'policy_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForReasonType($query, string $reasonType)
    {
        return $query->where('absence_reason_type', $reasonType);
    }

    public function scopeInDayRange($query, int $days)
    {
        return $query->where('day_from', '<=', $days)
            ->where('day_to', '>=', $days);
    }

    /**
     * Find applicable absence policy
     */
    public static function findApplicablePolicy(
        ?int $policyId,
        string $absenceReasonType,
        int $absenceDays
    ): ?self {
        return static::active()
            ->where('policy_id', $policyId)
            ->forReasonType($absenceReasonType)
            ->inDayRange($absenceDays)
            ->orderBy('day_from', 'desc')
            ->first();
    }
}






