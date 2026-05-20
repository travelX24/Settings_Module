<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Employee;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Athka\SystemSettings\Models\ApprovalTask;
use Athka\SystemSettings\Services\EmployeeService;
use Athka\SystemSettings\Services\Approvals\ApprovalService;

class ApprovalInboxController extends Controller
{
    protected $employeeService;
    protected $approvalService;

    public function __construct(EmployeeService $employeeService, ApprovalService $approvalService)
    {
        $this->employeeService = $employeeService;
        $this->approvalService = $approvalService;
    }

    public function summary(Request $request)
    {
        $employee = $this->employeeService->resolve();
        if (!$employee) return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);

        $companyId = $this->employeeService->getCompanyId();

        // ✅ Optimized: Tasks are created at submission time — no need for expensive scan here.
        // getTaskSummary is a single fast GROUP BY query on approval_tasks.
        $counts = $this->approvalService->getTaskSummary($employee->id, $companyId);

        $data = [];
        $labels = $this->getLabels();
        foreach ($counts as $key => $count) {
            $data[] = [
                'key'           => $key,
                'pending_count' => (int) $count,
                'label_ar'      => $labels[$key]['ar'] ?? $key,
                'label_en'      => $labels[$key]['en'] ?? $key,
            ];
        }

        return response()->json([
            'ok'           => true,
            'data'         => $data,
            'total_pending' => array_sum($counts),
            'is_approver'  => true,
        ]);
    }

    public function inbox(Request $request)
    {
        $employee = $this->employeeService->resolve();
        if (!$employee) return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);

        $companyId = $this->employeeService->getCompanyId();
        $type = $request->query('type', 'all');
        $status = $request->query('status', 'pending');
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $isAr = str_contains($request->header('Accept-Language', 'ar'), 'ar');
        $labels = $this->getLabels();
        $currentMonth = now()->format('Y-m');

        $query = ApprovalTask::where('company_id', $companyId)
            ->where('approver_employee_id', $employee->id);

        if ($status === 'pending') {
            $query->where('status', 'pending');
        } else {
            $query->whereIn('status', ['approved', 'rejected']);
        }

        if ($type !== 'all') {
            $query->where('operation_key', $type);
        }

        $paginator = $query->latest('id')->paginate($perPage);
        $tasks = $paginator->items();

        if (empty($tasks)) {
            return response()->json([
                'ok' => true,
                'data' => [],
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'total'        => $paginator->total(),
                ],
            ]);
        }

        // ──────────────────────────────────────────────────────────────────────
        // ✅ BULK PREFETCH: Group tasks by type and load all raw rows at once
        // ──────────────────────────────────────────────────────────────────────
        $groupedByType = [];
        foreach ($tasks as $task) {
            $groupedByType[$task->approvable_type][] = $task->approvable_id;
        }

        // Map: type => [ id => rawRow ]
        $rawDataMap = [];
        foreach ($groupedByType as $appType => $ids) {
            $src = $this->approvalService->getRequestSource($appType);
            if (!$src) continue;
            $rows = DB::table($src['table'])->whereIn($src['idCol'], $ids)->get();
            foreach ($rows as $row) {
                $rawDataMap[$appType][$row->{$src['idCol']}] = (array)$row;
            }
        }

        // ✅ Collect all unique employee IDs from raw rows
        $employeeIds = [];
        foreach ($rawDataMap as $appType => $rows) {
            $src = $this->approvalService->getRequestSource($appType);
            if (!$src || !$src['employeeCol']) continue;
            foreach ($rows as $row) {
                $eid = $row[$src['employeeCol']] ?? null;
                if ($eid) $employeeIds[] = (int)$eid;
            }
        }
        $employeeIds = array_unique($employeeIds);

        // ✅ Bulk load employees, departments, job titles
        $employeesMap = [];
        $departmentsMap = [];
        $jobTitlesMap = [];

        if (!empty($employeeIds)) {
            $empRows = DB::table('employees')
                ->whereIn('id', $employeeIds)
                ->get(['id', 'name_ar', 'name_en', 'department_id', 'job_title_id']);

            $deptIds = [];
            $jtIds   = [];
            foreach ($empRows as $emp) {
                $employeesMap[$emp->id] = $emp;
                if ($emp->department_id) $deptIds[] = $emp->department_id;
                if ($emp->job_title_id)  $jtIds[]   = $emp->job_title_id;
            }

            if (!empty($deptIds)) {
                $depts = DB::table('departments')->whereIn('id', array_unique($deptIds))->get(['id', 'name']);
                foreach ($depts as $d) $departmentsMap[$d->id] = $d->name ?? '';
            }
            if (!empty($jtIds)) {
                $jts = DB::table('job_titles')->whereIn('id', array_unique($jtIds))->get(['id', 'name']);
                foreach ($jts as $jt) $jobTitlesMap[$jt->id] = $jt->name ?? '';
            }
        }

        // ✅ Bulk load leave policies (for 'leaves' type only)
        $leavePoliciesMap = [];
        if (isset($rawDataMap['leaves'])) {
            $policyIds = array_unique(array_filter(array_column(array_values($rawDataMap['leaves']), 'leave_policy_id')));
            if (!empty($policyIds)) {
                $policyTable = 'leave_policies';
                $policyCols = ['id', 'days_per_year', 'leave_type', 'policy_year_id'];
                foreach (['name_ar', 'name_en', 'name'] as $col) {
                    if (Schema::hasColumn($policyTable, $col)) $policyCols[] = $col;
                }
                $policyRows = DB::table($policyTable)->whereIn('id', $policyIds)->get($policyCols);
                foreach ($policyRows as $p) $leavePoliciesMap[$p->id] = $p;
            }
        }

        // ✅ Bulk load consumed leave days per (employee, policy)
        $consumedBalanceMap = [];  // key: "{employeeId}_{policyId}"
        $monthlyLeaveMap   = [];   // key: "{employeeId}_{policyId}"
        if (isset($rawDataMap['leaves']) && !empty($employeeIds)) {
            $src = $this->approvalService->getRequestSource('leaves');
            if ($src) {
                $policyIds = array_keys($leavePoliciesMap);
                if (!empty($policyIds)) {
                    $consumedRows = DB::table($src['table'])
                        ->whereIn($src['employeeCol'], $employeeIds)
                        ->whereIn('leave_policy_id', $policyIds)
                        ->where('status', 'approved')
                        ->select($src['employeeCol'], 'leave_policy_id', DB::raw('SUM(requested_days) as consumed'))
                        ->groupBy($src['employeeCol'], 'leave_policy_id')
                        ->get();
                    foreach ($consumedRows as $r) {
                        $consumedBalanceMap[$r->{$src['employeeCol']} . '_' . $r->leave_policy_id] = (float)$r->consumed;
                    }

                    $monthlyRows = DB::table($src['table'])
                        ->whereIn($src['employeeCol'], $employeeIds)
                        ->whereIn('leave_policy_id', $policyIds)
                        ->where('status', 'approved')
                        ->where('start_date', 'like', $currentMonth . '%')
                        ->select($src['employeeCol'], 'leave_policy_id', DB::raw('SUM(requested_days) as taken'))
                        ->groupBy($src['employeeCol'], 'leave_policy_id')
                        ->get();
                    foreach ($monthlyRows as $r) {
                        $monthlyLeaveMap[$r->{$src['employeeCol']} . '_' . $r->leave_policy_id] = (float)$r->taken;
                    }
                }
            }
        }

        // ✅ Bulk load monthly permission minutes
        $monthlyPermMap = []; // key: "{employeeId}"
        if (isset($rawDataMap['permissions']) && !empty($employeeIds)) {
            $src = $this->approvalService->getRequestSource('permissions');
            if ($src) {
                $dateCol = Schema::hasColumn($src['table'], 'permission_date') ? 'permission_date' : 'date';
                $permRows = DB::table($src['table'])
                    ->whereIn($src['employeeCol'], $employeeIds)
                    ->where('status', 'approved')
                    ->where($dateCol, 'like', $currentMonth . '%')
                    ->select($src['employeeCol'], DB::raw('SUM(minutes) as total_minutes'))
                    ->groupBy($src['employeeCol'])
                    ->get();
                foreach ($permRows as $r) {
                    $monthlyPermMap[$r->{$src['employeeCol']}] = (int)$r->total_minutes;
                }
            }
        }

        // ──────────────────────────────────────────────────────────────────────
        // ✅ Assemble final response using pre-fetched maps (zero extra queries)
        // ──────────────────────────────────────────────────────────────────────
        foreach ($tasks as $task) {
            $src = $this->approvalService->getRequestSource($task->approvable_type);
            $rawData = $rawDataMap[$task->approvable_type][$task->approvable_id] ?? null;

            if (!$rawData || !$src) {
                $task->request = [
                    'id'         => $task->approvable_id,
                    'status'     => 'not_found',
                    'reason'     => 'Original request data missing',
                    'creator'    => 'Unknown',
                    'type_label' => $task->approvable_type,
                ];
                continue;
            }

            $data = $rawData;
            $data['id']            = (int) $data['id'];
            $data['status']        = (string) ($data['status'] ?? 'pending');
            $data['reason']        = (string) ($data['reason'] ?? '');
            $data['reject_reason'] = (string) ($data['reject_reason'] ?? '');

            // Creator Info (from pre-fetched map)
            $employeeCol = $src['employeeCol'];
            $requesterId = (int) ($data[$employeeCol] ?? 0);
            $creator = $requesterId ? ($employeesMap[$requesterId] ?? null) : null;

            if ($creator) {
                $data['creator']       = $isAr ? ($creator->name_ar ?? $creator->name_en) : ($creator->name_en ?? $creator->name_ar);
                $data['employee_name'] = $data['creator'];
                $data['employee']      = $data['creator'];
                $data['department']    = $creator->department_id ? ($departmentsMap[$creator->department_id] ?? '') : '';
                $data['job_title']     = $creator->job_title_id  ? ($jobTitlesMap[$creator->job_title_id]   ?? '') : '';
            } else {
                $data['creator'] = $data['employee_name'] = $data['employee'] = 'Unknown';
                $data['department'] = $data['job_title'] = '';
            }

            // Type Label
            $data['type_label'] = $isAr
                ? ($labels[$task->approvable_type]['ar'] ?? $task->approvable_type)
                : ($labels[$task->approvable_type]['en'] ?? $task->approvable_type);

            // Date normalization
            $data['request_date'] = (string) ($data['created_at'] ?? $data['requested_at'] ?? '');
            $data['from_date']    = (string) ($data['start_date'] ?? $data['date'] ?? $data['permission_date'] ?? '');
            $data['to_date']      = (string) ($data['end_date']   ?? $data['date'] ?? $data['permission_date'] ?? '');
            $data['from_time']    = (string) ($data['from_time'] ?? '');
            $data['to_time']      = (string) ($data['to_time'] ?? '');

            // Leave Specifics (from pre-fetched maps)
            if ($task->approvable_type === 'leaves') {
                $policyId = (int) ($data['leave_policy_id'] ?? 0);
                $policy   = $policyId ? ($leavePoliciesMap[$policyId] ?? null) : null;

                $policyNameAr = $policy->name_ar ?? $policy->name ?? null;
                $policyNameEn = $policy->name_en ?? $policy->name ?? null;
                $data['leave_type']     = $policy ? ($isAr ? ($policyNameAr ?: $policyNameEn) : ($policyNameEn ?: $policyNameAr)) : 'Leave';
                $data['requested_days'] = (string) ($data['requested_days'] ?? '0');

                if ($requesterId > 0 && $policy) {
                    $total    = (float)($policy->days_per_year ?? 0);
                    $consumed = (float)($consumedBalanceMap[$requesterId . '_' . $policyId] ?? 0);
                    $totalStr    = ($total == (int)$total) ? (int)$total : $total;
                    $consumedStr = ($consumed == (int)$consumed) ? (int)$consumed : $consumed;
                    $data['balance']             = $consumedStr . ' / ' . $totalStr;
                    $data['monthly_taken_days']  = (float)($monthlyLeaveMap[$requesterId . '_' . $policyId] ?? 0);
                } else {
                    $data['balance']            = '';
                    $data['monthly_taken_days'] = 0;
                }
            }

            // Permission Specifics (from pre-fetched maps)
            if ($task->approvable_type === 'permissions') {
                $data['leave_type']            = $isAr ? 'إذن' : 'Permission';
                $data['permission_date']       = (string) ($data['date'] ?? $data['permission_date'] ?? '');
                $data['monthly_taken_minutes'] = $requesterId ? ($monthlyPermMap[$requesterId] ?? 0) : 0;
            }

            // Mission Specifics
            if ($task->approvable_type === 'missions') {
                $data['requested_at'] = (string) ($data['created_at'] ?? '');
                $data['start_date']   = (string) ($data['start_date'] ?? '');
                $data['end_date']     = (string) ($data['end_date'] ?? '');
                $data['type']         = (string) ($data['type'] ?? 'full_day');
                $data['destination']  = (string) ($data['destination'] ?? '');
            }

            $task->request = $data;
        }

        return response()->json([
            'ok' => true,
            'data' => $tasks,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function approve(Request $request, string $type, int $id)
    {
        return $this->processAction($request, $type, $id, 'approved');
    }

    public function reject(Request $request, string $type, int $id)
    {
        return $this->processAction($request, $type, $id, 'rejected');
    }

    protected function processAction(Request $request, string $type, int $id, string $status)
    {
        $employee = $this->employeeService->resolve();
        if (!$employee) return response()->json(['ok' => false, 'message' => 'Employee not found'], 403);

        $task = ApprovalTask::where('approvable_type', $type)
            ->where('approvable_id', $id)
            ->where('approver_employee_id', $employee->id)
            ->where('status', 'pending')
            ->first();

        if (!$task) return response()->json(['ok' => false, 'message' => 'Task not found or already processed'], 404);

        $comment = $request->input('comment');
        $this->approvalService->processTask($task, $employee->id, $status, $comment);

        return response()->json(['ok' => true, 'message' => "Request $status successfully"]);
    }

    /**
     * Trigger task generation for a request.
     * Handles various signatures from other modules.
     */
    public function ensureTasksForRequest($param1, $param2, $param3 = null): void
    {
        $companyId = null;
        $type = null;
        $requestId = null;
        $src = null;

        if (is_array($param1) && isset($param1['table'])) {
            // Signature: ($src, $requestId)
            $src = $param1;
            $requestId = (int) $param2;
            $companyId = (int) ($src['last_company_id'] ?? $this->employeeService->getCompanyId());
        } else {
            // Signature: ($companyId, $type, $id)
            $companyId = (int) $param1;
            $type = (string) $param2;
            $requestId = (int) $param3;
            $src = $this->approvalService->getRequestSource($type);
        }

        if (!$src || !$requestId) return;

        $request = DB::table($src['table'])->where($src['idCol'], $requestId)->first();
        if (!$request) return;

        $this->approvalService->ensureTasksForRequest($src, $request, $companyId);
    }


    protected function getLabels()
    {
        return [
            'leaves' => ['ar' => 'إجازة', 'en' => 'Leave'],
            'permissions' => ['ar' => 'إذن', 'en' => 'Permission'],
            'missions' => ['ar' => 'مهمة', 'en' => 'Mission'],
            'replacements' => ['ar' => 'بديل', 'en' => 'Replacement'],
        ];
    }
}
