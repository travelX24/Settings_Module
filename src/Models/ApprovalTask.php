<?php

namespace Athka\SystemSettings\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalTask extends Model
{
    protected $table = 'approval_tasks';

    protected $fillable = [
        'company_id',
        'operation_key',
        'approvable_type',
        'approvable_id',
        'request_employee_id',
        'position',
        'approver_employee_id',
        'status',
        'acted_by_employee_id',
        'acted_at',
        'comment',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];
}
