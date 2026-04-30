<?php

namespace Athka\SystemSettings\Services\Approvals;

use Athka\SystemSettings\Models\ApprovalPolicy;
use Athka\SystemSettings\Models\ApprovalTask;
use Athka\SystemSettings\Models\ApprovalTaskDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ApprovalService
{
    /**
     * Map request types to DB tables with column detection.
     */
    public function getRequestSource(string $type): ?array
    {
        $map = [
            'leaves' => [
                'tables' => ['attendance_leave_requests', 'attendance_leave_cut_requests', 'leave_requests', 'employee_leave_requests'],
                'operation_key' => 'leaves',
            ],
            'permissions' => [
                'tables' => ['attendance_permission_requests', 'permission_requests', 'employee_permission_requests'],
                'operation_key' => 'permissions',
            ],
            'missions' => [
                'tables' => ['attendance_mission_requests'],
                'operation_key' => 'missions',
            ],
            'replacements' => [
                'tables' => ['attendance_leave_requests'],
                'operation_key' => 'replacements',
            ],
        ];

        if (!isset($map[$type])) return null;

        $table = null;
        foreach ($map[$type]['tables'] as $t) {
            if (Schema::hasTable($t)) { $table = $t; break; }
        }
        if (!$table) return null;

        $idCol = Schema::hasColumn($table, 'id') ? 'id' : null;
        if (!$idCol) return null;

        $companyCol = $this->detectCompanyColumn($table);
        $employeeCol = Schema::hasColumn($table, 'employee_id')
            ? 'employee_id'
            : (Schema::hasColumn($table, 'user_id') ? 'user_id' : null);

        $approvalStatusCol = Schema::hasColumn($table, 'approval_status') ? 'approval_status' : null;
        $statusCol = Schema::hasColumn($table, 'status') ? 'status' : null;

        $updatedAtCol = Schema::hasColumn($table, 'updated_at') ? 'updated_at' : null;
        $createdAtCol = Schema::hasColumn($table, 'created_at') ? 'created_at' : null;

        return [
            'type' => $type,
            'table' => $table,
            'operation_key' => $map[$type]['operation_key'],
            'idCol' => $idCol,
            'companyCol' => $companyCol,
            'employeeCol' => $employeeCol,
            'approvalStatusCol' => $approvalStatusCol,
            'statusCol' => $statusCol,
            'createdAtCol' => $createdAtCol,
            'updatedAtCol' => $updatedAtCol,
        ];
    }

    /**
     * Detect company column.
     */
    public function detectCompanyColumn(string $table): ?string
    {
        foreach (['saas_company_id', 'company_id'] as $c) {
            if (Schema::hasColumn($table, $c)) return $c;
        }
        return null;
    }

    /**
     * Resolve manager.
     */
    public function resolveDirectManagerId(int $employeeId): int
    {
        $candidates = ['direct_manager_id', 'manager_id', 'reports_to_id', 'supervisor_id', 'line_manager_id'];
        $col = null;
        foreach ($candidates as $c) {
            if (Schema::hasTable('employees') && Schema::hasColumn('employees', $c)) { $col = $c; break; }
        }
        if (!$col) return 0;
        return (int) DB::table('employees')->where('id', $employeeId)->value($col);
    }

    public function resolvePolicyForEmployee(string $operationKey, int $employeeId, int $companyId)
    {
        $policies = DB::table('approval_policies')
            ->where('company_id', $companyId)
            ->where('operation_key', $operationKey)
            ->where('is_active', true)
            ->get();

        if ($policies->isEmpty()) {
            return null;
        }

        $employee = DB::table('employees')->where('id', $employeeId)->first();
        if (!$employee) return null;

        foreach ($policies as $policy) {
            if ($policy->scope_type === 'all') {
                return $policy;
            }

            $scopes = DB::table('approval_policy_scopes')
                ->where('policy_id', $policy->id)
                ->pluck('scope_id')
                ->map(fn($id) => (string)$id)
                ->toArray();

            if ($policy->scope_type === 'employees' && in_array((string)$employeeId, $scopes, true)) {
                return $policy;
            }

            if ($policy->scope_type === 'departments' && in_array((string)$employee->department_id, $scopes, true)) {
                return $policy;
            }

            if ($policy->scope_type === 'branches' && in_array((string)$employee->branch_id, $scopes, true)) {
                return $policy;
            }
        }

        return null;
    }

    /**
     * Check if an employee has ANY approvers setup (either via Policy or Direct Manager).
     */
    public function hasApproversForEmployee(string $operationKey, int $employeeId, int $companyId): bool
    {
        $policy = $this->resolvePolicyForEmployee($operationKey, $employeeId, $companyId);

        if (!$policy) {
            // No policy means no strict workflow defined.
            return false;
        }

        // Has policy, check if it has steps
        return DB::table('approval_policy_steps')
            ->where('policy_id', $policy->id)
            ->exists();
    }

    /**
     * Summary of pending tasks.
     */
    public function getTaskSummary(int $employeeId, int $companyId): array
    {
        $counts = ApprovalTask::where('approver_employee_id', $employeeId)
            ->where('status', 'pending')
            ->where('company_id', $companyId)
            ->selectRaw('operation_key, count(*) as count')
            ->groupBy('operation_key')
            ->pluck('count', 'operation_key')
            ->all();

        return $counts;
    }

    /**
     * Ensure tasks exist for all pending requests of a specific source.
     */
    public function ensureTasksForPendingRequests(array $src, int $companyId): void
    {
        $uCol = $src['approvalStatusCol'] ?: $src['statusCol'];
        if (!$uCol) return;

        $pendingRequests = DB::table($src['table'])
            ->where($src['companyCol'], $companyId)
            ->where($uCol, 'pending')
            ->where($src['createdAtCol'], '>=', now()->subMonths(3))
            ->get();

        foreach ($pendingRequests as $req) {
            $this->ensureTasksForRequest($src, $req, $companyId);
        }
    }

    /**
     * Ensure tasks exist for a single request.
     */
    public function ensureTasksForRequest(array $src, object $request, int $companyId): void
    {
        $requestId = $request->{$src['idCol']};
        
        $existing = ApprovalTask::where('approvable_type', $src['type'])
            ->where('approvable_id', $requestId)
            ->exists();

        if ($existing) return;

        // 1. Find Policy
        $policy = $this->resolvePolicyForEmployee($src['operation_key'], (int)$request->{$src['employeeCol']}, $companyId);

        if (!$policy) {
            // No policy? Default to direct manager if possible
            $managerId = $this->resolveDirectManagerId((int)$request->{$src['employeeCol']});
            if ($managerId > 0) {
                ApprovalTask::create([
                    'company_id' => $companyId,
                    'operation_key' => $src['operation_key'],
                    'approvable_type' => $src['type'],
                    'approvable_id' => $requestId,
                    'request_employee_id' => $request->{$src['employeeCol']},
                    'position' => 1,
                    'approver_employee_id' => $managerId,
                    'status' => 'pending',
                ]);
            }
            return;
        }



        // 2. Fetch steps
        $steps = DB::table('approval_policy_steps')
            ->where('policy_id', $policy->id)
            ->orderBy('position')
            ->get();



        if ($steps->isEmpty()) {
            return;
        }

        foreach ($steps as $step) {
            $approverId = 0;

            if ($step->approver_type === 'direct_manager') {
                $approverId = $this->resolveDirectManagerId((int)$request->{$src['employeeCol']});
            } elseif ($step->approver_type === 'user') {
                $approverId = DB::table('users')->where('id', $step->approver_id)->value('employee_id') ?: 0;
            } elseif ($step->approver_type === 'employee') {
                $approverId = $step->approver_id;
            }

            if ($approverId > 0) {
                ApprovalTask::create([
                    'company_id' => $companyId,
                    'operation_key' => $src['operation_key'],
                    'approvable_type' => $src['type'],
                    'approvable_id' => $requestId,
                    'request_employee_id' => $request->{$src['employeeCol']},
                    'position' => $step->position,
                    'approver_employee_id' => $approverId,
                    'status' => ((int) $step->position === 1) ? 'pending' : 'waiting',
                ]);
            } else {

            }
        }
    }

    /**
     * Process task action (Approve/Reject).
     */
    public function processTask(ApprovalTask $task, int $actedByEmployeeId, string $status, ?string $comment = null): bool
    {
        return DB::transaction(function() use ($task, $actedByEmployeeId, $status, $comment) {
            $task->update([
                'status' => $status,
                'acted_by_employee_id' => $actedByEmployeeId,
                'acted_at' => now(),
                'comment' => $comment ?: '',
            ]);

            $src = $this->getRequestSource($task->approvable_type);

            if ($status === 'rejected') {
                $this->cancelRemainingTasks($task, 'Rejected by another approver');
                $this->updateRequestStatus($src, $task->approvable_id, 'rejected');
            } else if ($status === 'approved') {
                $this->handleSuccessiveApprovals($task, $src);
            }

            return true;
        });
    }

    protected function cancelRemainingTasks(ApprovalTask $task, string $comment)
    {
        ApprovalTask::where('approvable_type', $task->approvable_type)
            ->where('approvable_id', $task->approvable_id)
            ->whereIn('status', ['pending', 'waiting'])
            ->where('id', '!=', $task->id)
            ->update(['status' => 'cancelled', 'comment' => $comment]);
    }

    protected function updateRequestStatus(?array $src, int $id, string $status)
    {
        if (!$src) return;
        $uCol = $src['approvalStatusCol'] ?: $src['statusCol'];
        if ($uCol) {
            DB::table($src['table'])->where($src['idCol'], $id)->update([$uCol => $status]);
        }
    }

    protected function handleSuccessiveApprovals(ApprovalTask $task, ?array $src)
    {
        // 1. Check if there are other pending tasks at the same position
        $otherPendingAtSameSeq = ApprovalTask::where('approvable_type', $task->approvable_type)
            ->where('approvable_id', $task->approvable_id)
            ->where('position', $task->position)
            ->where('status', 'pending')
            ->exists();

        if ($otherPendingAtSameSeq) return; 

        // 2. Activate next position
        $nextPos = (int)$task->position + 1;
        $waitingTasks = ApprovalTask::where('approvable_type', $task->approvable_type)
            ->where('approvable_id', $task->approvable_id)
            ->where('position', $nextPos)
            ->where('status', 'waiting')
            ->get();

        foreach ($waitingTasks as $wt) {
            $wt->update(['status' => 'pending']);
        }

        // 3. Mark request as approved if no more tasks
        $hasMore = ApprovalTask::where('approvable_type', $task->approvable_type)
            ->where('approvable_id', $task->approvable_id)
            ->whereIn('status', ['pending', 'waiting'])
            ->exists();

        if (!$hasMore) {
            $this->updateRequestStatus($src, $task->approvable_id, 'approved');
        }
    }
}
