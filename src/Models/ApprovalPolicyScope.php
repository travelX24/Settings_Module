<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalPolicyScope extends Model
{
    protected $table = 'approval_policy_scopes';

    protected $fillable = [
        'policy_id',
        'scope_id',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ApprovalPolicy::class, 'policy_id');
    }
}
