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
     * Static in-memory cache for getRequestSource() results.
     * Avoids repeated Schema::hasTable / Schema::hasColumn calls per request.
     */
    protected static array $sourceCache = [];

    /**
     * Static in-memory cache for detected manager column.
     */
    protected static ?string $managerColCache = null;
    protected static bool $managerColResolved = false;

    /**
     * Map request types to DB tables with column detection.
     * Results are cached in-memory to avoid repeated Schema queries per request.
     */
    public function getRequestSource(string $type): ?array
    {
        // Return from static cache if already resolved
        if (array_key_exists($type, static::$sourceCache)) {
            return static::$sourceCache[$type];
        }

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

        if (!isset($map[$type])) {
            return static::$sourceCache[$type] = null;
        }

        $table = null;
        foreach ($map[$type]['tables'] as $t) {
            if (Schema::hasTable($t)) { $table = $t; break; }
        }
        if (!$table) {
            return static::$sourceCache[$type] = null;
        }

        $idCol = Schema::hasColumn($table, 'id') ? 'id' : null;
        if (!$idCol) {
            return static::$sourceCache[$type] = null;
        }

        $companyCol = $this->detectCompanyColumn($table);
        $employeeCol = Schema::hasColumn($table, 'employee_id')
            ? 'employee_id'
            : (Schema::hasColumn($table, 'user_id') ? 'user_id' : null);

        $approvalStatusCol = Schema::hasColumn($table, 'approval_status') ? 'approval_status' : null;
        $statusCol         = Schema::hasColumn($table, 'status')          ? 'status'          : null;
        $updatedAtCol      = Schema::hasColumn($table, 'updated_at')      ? 'updated_at'      : null;
        $createdAtCol      = Schema::hasColumn($table, 'created_at')      ? 'created_at'      : null;

        return static::$sourceCache[$type] = [
            'type'              => $type,
            'table'             => $table,
            'operation_key'     => $map[$type]['operation_key'],
            'idCol'             => $idCol,
            'companyCol'        => $companyCol,
            'employeeCol'       => $employeeCol,
            'approvalStatusCol' => $approvalStatusCol,
            'statusCol'         => $statusCol,
            'createdAtCol'      => $createdAtCol,
            'updatedAtCol'      => $updatedAtCol,
        ];
    }

    /**
     * Detect company column. Cached per table.
     */
    protected static array $companyColCache = [];

    public function detectCompanyColumn(string $table): ?string
    {
        if (array_key_exists($table, static::$companyColCache)) {
            return static::$companyColCache[$table];
        }
        foreach (['saas_company_id', 'company_id'] as $c) {
            if (Schema::hasColumn($table, $c)) {
                return static::$companyColCache[$table] = $c;
            }
        }
        return static::$companyColCache[$table] = null;
    }

    /**
     * Resolve manager.
     */
    /**
     * Resolve manager ID. Caches the column name to avoid repeated Schema checks.
     */
    public function resolveDirectManagerId(int $employeeId): int
    {
        if (!static::$managerColResolved) {
            $candidates = ['direct_manager_id', 'manager_id', 'reports_to_id', 'supervisor_id', 'line_manager_id'];
            foreach ($candidates as $c) {
                if (Schema::hasTable('employees') && Schema::hasColumn('employees', $c)) {
                    static::$managerColCache = $c;
                    break;
                }
            }
            static::$managerColResolved = true;
        }
        if (!static::$managerColCache) return 0;
        return (int) DB::table('employees')->where('id', $employeeId)->value(static::$managerColCache);
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

        // Sort policies by priority (employee > department > job_title > branch > all)
        $sortedPolicies = $policies->sortBy(function($policy) {
            return match ($policy->scope_type) {
                'employee'   => 1,
                'department' => 2,
                'job_title'  => 3,
                'branch'     => 4,
                default      => 5, // all
            };
        });

        foreach ($sortedPolicies as $policy) {
            if ($policy->scope_type === 'all') {
                return $policy;
            }

            $scopes = DB::table('approval_policy_scopes')
                ->where('policy_id', $policy->id)
                ->pluck('scope_id')
                ->map(fn($id) => (string)$id)
                ->toArray();

            if ($policy->scope_type === 'employee' && in_array((string)$employeeId, $scopes, true)) {
                return $policy;
            }

            if ($policy->scope_type === 'department' && in_array((string)($employee->department_id ?? ''), $scopes, true)) {
                return $policy;
            }

            if ($policy->scope_type === 'job_title' && in_array((string)($employee->job_title_id ?? $employee->job_title ?? ''), $scopes, true)) {
                return $policy;
            }

            if ($policy->scope_type === 'branch' && in_array((string)($employee->branch_id ?? ''), $scopes, true)) {
                return $policy;
            }
        }

        return null;
    }

    /**
     * Check if any active policies exist for an operation.
     */
    public function hasActivePolicies(string $operationKey, int $companyId): bool
    {
        return DB::table('approval_policies')
            ->where('company_id', $companyId)
            ->where('operation_key', $operationKey)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check if an employee has ANY approvers setup (either via Policy or Direct Manager).
     */
    public function hasApproversForEmployee(string $operationKey, int $employeeId, int $companyId, &$reason = null): bool
    {
        $policy = $this->resolvePolicyForEmployee($operationKey, $employeeId, $companyId);

        if (!$policy) {
            $reason = 'no_matching_policy';
            return false;
        }

        // Fetch steps and verify they can all be resolved
        $steps = DB::table('approval_policy_steps')
            ->where('policy_id', $policy->id)
            ->orderBy('position')
            ->get();

        if ($steps->isEmpty()) {
            $reason = 'no_steps_defined';
            return false;
        }

        foreach ($steps as $step) {
            $approverId = 0;
            if ($step->approver_type === 'direct_manager') {
                $approverId = $this->resolveDirectManagerId($employeeId);
                if ($approverId <= 0) {
                    $reason = 'missing_direct_manager';
                    return false;
                }
            } elseif ($step->approver_type === 'user') {
                // If specific user, check if they have a linked employee
                $approverId = DB::table('users')->where('id', $step->approver_id)->value('employee_id') ?: 0;
                if ($approverId <= 0) {
                    $reason = 'invalid_user_approver';
                    return false;
                }
            } elseif ($step->approver_type === 'employee') {
                $approverId = (int) $step->approver_id;
                if ($approverId <= 0) {
                    $reason = 'invalid_employee_approver';
                    return false;
                }
            }

            // Final fallback check
            if ($approverId <= 0) {
                $reason = 'unresolvable_approver_step';
                return false;
            }
        }

        return true;
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
            // Try to use Eloquent model to trigger observers
            $modelClass = $this->guessModelForTable($src['table']);
            if ($modelClass && class_exists($modelClass)) {
                $row = $modelClass::find($id);
                if ($row) {
                    $row->update([$uCol => $status]);
                    $this->applyPostStatusEffects($src, $id);
                    return;
                }
            }

            // Fallback to DB table if no model found
            DB::table($src['table'])->where($src['idCol'], $id)->update([$uCol => $status]);
            $this->applyPostStatusEffects($src, $id);
        }
    }

    protected function applyPostStatusEffects(array $src, int $id): void
    {
        if (($src['table'] ?? null) !== 'attendance_leave_requests') {
            return;
        }

        if (!class_exists(\Athka\Attendance\Models\AttendanceLeaveRequest::class)
            || !class_exists(\Athka\Attendance\Services\LeaveApprovalImpactService::class)) {
            return;
        }

        $leave = \Athka\Attendance\Models\AttendanceLeaveRequest::find($id);
        if ($leave) {
            app(\Athka\Attendance\Services\LeaveApprovalImpactService::class)->apply($leave);
        }
    }

    protected function guessModelForTable(string $table): ?string
    {
        $map = [
            'attendance_leave_requests'      => \Athka\Attendance\Models\AttendanceLeaveRequest::class,
            'attendance_leave_cut_requests'  => \Athka\Attendance\Models\AttendanceLeaveCutRequest::class,
            'attendance_permission_requests' => \Athka\Attendance\Models\AttendancePermissionRequest::class,
            'attendance_mission_requests'    => \Athka\Attendance\Models\AttendanceMissionRequest::class,
        ];
        return $map[$table] ?? null;
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
