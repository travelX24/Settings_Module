<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class AttendancePenaltyPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_id',
        'violation_type',
        'minutes_from',
        'minutes_to',
        'recurrence_from',
        'recurrence_to',
        'penalty_action',
        'deduction_type',
        'deduction_value',
        'suspension_days',
        'notification_message',
        'is_active',
        'saas_company_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'minutes_from' => 'integer',
        'minutes_to' => 'integer',
        'recurrence_from' => 'integer',
        'recurrence_to' => 'integer',
        'deduction_value' => 'decimal:2',
        'suspension_days' => 'integer',
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

    public function scopeForViolation($query, string $violationType)
    {
        return $query->where('violation_type', $violationType);
    }

    public function scopeInMinutesRange($query, int $minutes)
    {
        return $query->where('minutes_from', '<=', $minutes)
            ->where('minutes_to', '>=', $minutes);
    }

    public function scopeInRecurrenceRange($query, int $count)
    {
        return $query->where('recurrence_from', '<=', $count)
            ->where('recurrence_to', '>=', $count);
    }

    /**
     * Find applicable penalty for given violation
     */
    public static function findApplicablePenalty(
        ?int $policyId,
        string $violationType,
        int $minutes,
        int $recurrenceCount
    ): ?self {
        return static::active()
            ->where('policy_id', $policyId)
            ->forViolation($violationType)
            ->inMinutesRange($minutes)
            ->inRecurrenceRange($recurrenceCount)
            ->orderBy('recurrence_from', 'desc')
            ->first();
    }
}






