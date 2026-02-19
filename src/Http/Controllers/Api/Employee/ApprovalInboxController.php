<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Athka\SystemSettings\Models\ApprovalPolicy;
use Athka\SystemSettings\Models\ApprovalTask;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalInboxController extends Controller
{
    private function companyId(): int
    {
        if (app()->bound('currentCompany') && app('currentCompany')) {
            return (int) app('currentCompany')->id;
        }

        return (int) (Auth::user()->saas_company_id ?? 0);
    }

    private function currentEmployeeId(): int
    {
        $u = Auth::user();
        if (!$u) return 0;

        if (!empty($u->employee_id)) return (int) $u->employee_id;

        // fallback: لو عندنا employees.user_id
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'user_id')) {
            $empId = DB::table('employees')->where('user_id', (int) $u->id)->value('id');
            return (int) ($empId ?: 0);
        }

        return 0;
    }

    private function managerColumn(): ?string
    {
        $candidates = [
            'direct_manager_id',
            'manager_id',
            'reports_to_id',
            'supervisor_id',
            'line_manager_id',
        ];

        foreach ($candidates as $c) {
            if (Schema::hasTable('employees') && Schema::hasColumn('employees', $c)) return $c;
        }

        return null;
    }

    private function resolveDirectManagerId(int $employeeId): int
    {
        $col = $this->managerColumn();
        if (!$col) return 0;

        $mid = DB::table('employees')->where('id', $employeeId)->value($col);
        return (int) ($mid ?: 0);
    }

    private function detectCompanyColumn(string $table): ?string
    {
        foreach (['saas_company_id', 'company_id'] as $c) {
            if (Schema::hasColumn($table, $c)) return $c;
        }
        return null;
    }

    private function requestSource(string $type): ?array
    {
      $map = [
            'leaves' => [
                'tables' => [
                    'attendance_leave_requests',
                    'attendance_leave_cut_requests',
                    'leave_requests',
                    'employee_leave_requests',
                ],
                'operation_key' => 'leaves',
            ],
            'permissions' => [
                'tables' => [
                    'attendance_permission_requests',
                    'permission_requests',
                    'employee_permission_requests',
                ],
                'operation_key' => 'permissions',
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

        // الأفضل approval_status، لو مش موجود لا نلمس status في approve/reject
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

    public function meta()
    {
        return response()->json([
            'ok' => true,
            'data' => [
                'company_id' => $this->companyId(),
                'current_employee_id' => $this->currentEmployeeId(),
                'manager_column' => $this->managerColumn(),
                'sources' => [
                    'leaves' => $this->requestSource('leaves'),
                    'permissions' => $this->requestSource('permissions'),
                ],
                'note' => 'If approvalStatusCol is null, approve/reject will not update request status; only tasks will be updated.',
            ],
        ]);
    }

    public function inbox(Request $request)
    {
        $employeeId = $this->currentEmployeeId();
        if ($employeeId < 1) {
            return response()->json(['ok' => false, 'error' => 'employee_not_found'], 403);
        }

        $type = (string) $request->query('type', 'all'); // all|leaves|permissions
        $ensure = (int) $request->query('ensure', 0) === 1;

        $types = $type === 'all' ? ['leaves', 'permissions'] : [$type];

        if ($ensure) {
            foreach ($types as $t) {
                $src = $this->requestSource($t);
                if ($src) {
                    $this->ensureTasksForPendingRequests($src);
                }
            }
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $q = ApprovalTask::query()
            ->where('company_id', $this->companyId())
            ->where('approver_employee_id', $employeeId)
            ->where('status', 'pending');

        if ($type !== 'all') {
            $q->where('approvable_type', $type);
        }

        $p = $q->latest('id')->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => $p->items(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
            ],
        ]);
    }

    public function approve(Request $request, string $type, int $id)
    {
        $employeeId = $this->currentEmployeeId();
        if ($employeeId < 1) return response()->json(['ok' => false, 'error' => 'employee_not_found'], 403);

        $src = $this->requestSource($type);
        if (!$src) return response()->json(['ok' => false, 'error' => 'unknown_type'], 404);

        $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        // ensure tasks exist for this request (lazy)
        $this->ensureTasksForRequest($src, $id);

        $task = ApprovalTask::query()
            ->where('company_id', $this->companyId())
            ->where('approvable_type', $type)
            ->where('approvable_id', $id)
            ->where('approver_employee_id', $employeeId)
            ->where('status', 'pending')
            ->orderBy('position')
            ->first();

        if (!$task) {
            return response()->json(['ok' => false, 'error' => 'no_pending_task_for_you'], 409);
        }

        DB::transaction(function () use ($task, $src, $id, $employeeId, $request, $type) {
            $task->update([
                'status' => 'approved',
                'acted_by_employee_id' => $employeeId,
                'acted_at' => now(),
                'comment' => (string) ($request->input('comment') ?? ''),
            ]);

            // move next waiting -> pending (skip skipped)
            $next = ApprovalTask::query()
                ->where('company_id', $task->company_id)
                ->where('approvable_type', $type)
                ->where('approvable_id', $id)
                ->where('status', 'waiting')
                ->orderBy('position')
                ->first();

            while ($next && ($next->approver_employee_id ?? 0) < 1) {
                $next->update(['status' => 'skipped']);
                $next = ApprovalTask::query()
                    ->where('company_id', $task->company_id)
                    ->where('approvable_type', $type)
                    ->where('approvable_id', $id)
                    ->where('status', 'waiting')
                    ->orderBy('position')
                    ->first();
            }

            if ($next) {
                $next->update(['status' => 'pending']);
                return;
            }

            // no more steps -> mark request approved (only if approval_status exists)
            $this->updateRequestApprovalStatus($src, $id, 'approved');
        });

        return response()->json(['ok' => true]);
    }

    public function reject(Request $request, string $type, int $id)
    {
        $employeeId = $this->currentEmployeeId();
        if ($employeeId < 1) return response()->json(['ok' => false, 'error' => 'employee_not_found'], 403);

        $src = $this->requestSource($type);
        if (!$src) return response()->json(['ok' => false, 'error' => 'unknown_type'], 404);

        $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        // ensure tasks exist
        $this->ensureTasksForRequest($src, $id);

        $task = ApprovalTask::query()
            ->where('company_id', $this->companyId())
            ->where('approvable_type', $type)
            ->where('approvable_id', $id)
            ->where('approver_employee_id', $employeeId)
            ->where('status', 'pending')
            ->orderBy('position')
            ->first();

        if (!$task) {
            return response()->json(['ok' => false, 'error' => 'no_pending_task_for_you'], 409);
        }

        DB::transaction(function () use ($task, $src, $id, $employeeId, $request, $type) {
            $task->update([
                'status' => 'rejected',
                'acted_by_employee_id' => $employeeId,
                'acted_at' => now(),
                'comment' => (string) $request->input('comment'),
            ]);

            // cancel other waiting/pending steps
            ApprovalTask::query()
                ->where('company_id', $task->company_id)
                ->where('approvable_type', $type)
                ->where('approvable_id', $id)
                ->whereIn('status', ['waiting', 'pending'])
                ->where('id', '!=', $task->id)
                ->update(['status' => 'canceled']);

            $this->updateRequestApprovalStatus($src, $id, 'rejected');
        });

        return response()->json(['ok' => true]);
    }

    public function timeline(string $type, int $id)
    {
        $employeeId = $this->currentEmployeeId();
        if ($employeeId < 1) return response()->json(['ok' => false, 'error' => 'employee_not_found'], 403);

        $tasks = ApprovalTask::query()
            ->where('company_id', $this->companyId())
            ->where('approvable_type', $type)
            ->where('approvable_id', $id)
            ->orderBy('position')
            ->get();

        return response()->json(['ok' => true, 'data' => $tasks]);
    }

    private function ensureTasksForPendingRequests(array $src): void
    {
        $pendingCol = $src['approvalStatusCol'] ?: $src['statusCol'];
        if (!$pendingCol) return;

        $q = DB::table($src['table'])->select([$src['idCol'] . ' as id']);

        if ($src['companyCol']) {
            $q->where($src['companyCol'], $this->companyId());
        }

        $q->where($pendingCol, 'pending')
        ->orderByDesc($src['idCol'])
        ->limit(200);


        foreach ($q->get() as $row) {
            $this->ensureTasksForRequest($src, (int) $row->id);
        }
    }

    private function ensureTasksForRequest(array $src, int $requestId): void
    {
        $exists = ApprovalTask::query()
            ->where('company_id', $this->companyId())
            ->where('approvable_type', $src['type'])
            ->where('approvable_id', $requestId)
            ->exists();

        if ($exists) return;

        $req = DB::table($src['table'])->where($src['idCol'], $requestId)->first();
        if (!$req) return;

        $requestEmployeeId = $this->resolveRequestEmployeeId($src, $req);
        if ($requestEmployeeId < 1) return;

        $policy = $this->resolvePolicyForEmployee($src['operation_key'], $requestEmployeeId);
        if (!$policy) return;

        $steps = $policy->steps()->orderBy('position')->get();

        $firstPendingAssigned = false;

        foreach ($steps as $s) {
            $type = (string) $s->approver_type;
            $approverId = 0;

            if ($type === 'user') {
                $approverId = (int) ($s->approver_id ?? 0);
            } else { // direct_manager
                $approverId = $this->resolveDirectManagerId($requestEmployeeId);
            }

            $status = 'waiting';

            if ($approverId < 1) {
                $status = 'skipped';
            } elseif (!$firstPendingAssigned) {
                $status = 'pending';
                $firstPendingAssigned = true;
            }

            ApprovalTask::create([
                'company_id' => $this->companyId(),
                'operation_key' => $src['operation_key'],
                'approvable_type' => $src['type'],
                'approvable_id' => $requestId,
                'request_employee_id' => $requestEmployeeId,
                'position' => (int) ($s->position ?? 1),
                'approver_employee_id' => $approverId > 0 ? $approverId : null,
                'status' => $status,
            ]);
        }

        // لو كل الخطوات skipped (مثلا لا يوجد مدير) نخليه pending = false، ما نغير حالة الطلب
    }

    private function resolveRequestEmployeeId(array $src, object $req): int
    {
        $col = $src['employeeCol'];
        if (!$col) return 0;

        $raw = (int) ($req->{$col} ?? 0);
        if ($raw < 1) return 0;

        // لو الجدول يستخدم user_id نحاول نرجعه employee_id
        if ($col === 'user_id' && Schema::hasTable('employees') && Schema::hasColumn('employees', 'user_id')) {
            $empId = DB::table('employees')->where('user_id', $raw)->value('id');
            return (int) ($empId ?: 0);
        }

        return $raw;
    }

    private function resolvePolicyForEmployee(string $operationKey, int $employeeId): ?ApprovalPolicy
    {
        $companyId = $this->companyId();

        $employee = DB::table('employees')->where('id', $employeeId)->first();
        if (!$employee) return null;

        $deptId = (int) ($employee->department_id ?? 0);
        $jobId = (int) ($employee->job_title_id ?? 0);
        $branchId = (int) ($employee->branch_id ?? 0);

       $policies = ApprovalPolicy::query()
            ->where('company_id', $companyId)
            ->where('operation_key', $operationKey)
            ->where('is_active', true)
            ->with(['scopes:id,policy_id,scope_id'])
            ->latest('id')
            ->get();

        // fallback: permissions تستخدم leaves إذا ما لها policy
        if ($policies->count() === 0 && $operationKey === 'permissions') {
            $policies = ApprovalPolicy::query()
                ->where('company_id', $companyId)
                ->where('operation_key', 'leaves')
                ->where('is_active', true)
                ->with(['scopes:id,policy_id,scope_id'])
                ->latest('id')
                ->get();
        }


        return $policies->first(function ($p) use ($employeeId, $deptId, $jobId, $branchId) {
            $type = (string) ($p->scope_type ?? 'all');
            if ($type === 'all') return true;

            $ids = $p->scopes->pluck('scope_id')->map(fn($v)=>(int)$v)->all();

            return match ($type) {
                'employee'   => in_array($employeeId, $ids, true),
                'department' => $deptId > 0 && in_array($deptId, $ids, true),
                'job_title'  => $jobId > 0 && in_array($jobId, $ids, true),
                'branch'     => $branchId > 0 && in_array($branchId, $ids, true),
                default      => false,
            };
        });
    }

    private function updateRequestApprovalStatus(array $src, int $requestId, string $status): void
    {
        $col = $src['approvalStatusCol'] ?: $src['statusCol'];
        if (!$col) return;

        $update = [$col => $status];


        if ($src['updatedAtCol']) {
            $update[$src['updatedAtCol']] = now();
        }

        DB::table($src['table'])
            ->where($src['idCol'], $requestId)
            ->update($update);
    }
}
