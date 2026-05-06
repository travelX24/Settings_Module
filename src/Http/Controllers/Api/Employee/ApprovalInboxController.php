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
        
        // Ensure tasks exist
        foreach (['leaves', 'permissions', 'missions'] as $type) {
            $src = $this->approvalService->getRequestSource($type);
            if ($src) $this->approvalService->ensureTasksForPendingRequests($src, $companyId);
        }

        $counts = $this->approvalService->getTaskSummary($employee->id, $companyId);
        
        $data = [];
        $labels = $this->getLabels();
        foreach ($counts as $key => $count) {
            $data[] = [
                'key' => $key,
                'pending_count' => (int) $count,
                'label_ar' => $labels[$key]['ar'] ?? $key,
                'label_en' => $labels[$key]['en'] ?? $key,
            ];
        }

        return response()->json([
            'ok' => true,
            'data' => $data,
            'total_pending' => array_sum($counts),
            'is_approver' => true // Since they hit this endpoint and got data
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

        foreach ($tasks as $task) {
            $task->request = $this->normalizeRequestData($task, $request);
        }

        return response()->json([
            'ok' => true,
            'data' => $tasks,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
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

    protected function normalizeRequestData($task, Request $request)
    {
        $src = $this->approvalService->getRequestSource($task->approvable_type);
        if (!$src) return null;

        $rawData = DB::table($src['table'])->where($src['idCol'], $task->approvable_id)->first();
        if (!$rawData) {
            return [
                'id' => $task->approvable_id,
                'status' => 'not_found',
                'reason' => 'Original request data missing',
                'creator' => 'Unknown',
                'type_label' => $task->approvable_type,
            ];
        }

        $data = (array) $rawData;
        $isAr = str_contains($request->header('Accept-Language', 'ar'), 'ar');

        // Common Fields
        $data['id'] = (int) $data['id'];
        $data['status'] = (string) ($data['status'] ?? 'pending');
        $data['reason'] = (string) ($data['reason'] ?? '');
        $data['reject_reason'] = (string) ($data['reject_reason'] ?? '');

        // Creator Info
        $employeeCol = $src['employeeCol'];
        if ($employeeCol && isset($data[$employeeCol])) {
            $creator = DB::table('employees')->where('id', $data[$employeeCol])->first(['name_ar', 'name_en', 'department_id', 'job_title_id']);
            $data['creator'] = $creator ? ($isAr ? ($creator->name_ar ?? $creator->name_en) : ($creator->name_en ?? $creator->name_ar)) : 'Unknown';
            $data['employee_name'] = $data['creator'];
            $data['employee'] = $data['creator'];

            if ($creator) {
                if ($creator->department_id) {
                    $dept = DB::table('departments')->where('id', $creator->department_id)->first(['name']);
                    $data['department'] = $dept->name ?? '';
                }
                if ($creator->job_title_id) {
                    $jt = DB::table('job_titles')->where('id', $creator->job_title_id)->first(['name']);
                    $data['job_title'] = $jt->name ?? '';
                }
            }
        } else {
            $data['creator'] = 'Unknown';
            $data['employee_name'] = 'Unknown';
            $data['employee'] = 'Unknown';
        }

        // Type Label
        $labels = $this->getLabels();
        $data['type_label'] = $isAr 
            ? ($labels[$task->approvable_type]['ar'] ?? $task->approvable_type) 
            : ($labels[$task->approvable_type]['en'] ?? $task->approvable_type);

        // Date fields normalization for Flutter models
        $data['request_date'] = (string) ($data['created_at'] ?? $data['requested_at'] ?? '');
        $data['from_date'] = (string) ($data['start_date'] ?? $data['date'] ?? $data['permission_date'] ?? '');
        $data['to_date'] = (string) ($data['end_date'] ?? $data['date'] ?? $data['permission_date'] ?? '');
        $data['from_time'] = (string) ($data['from_time'] ?? '');
        $data['to_time'] = (string) ($data['to_time'] ?? '');

        // Leave Specifics
        if ($task->approvable_type === 'leaves') {
            $policy = null;
            $policyId = (int) ($data['leave_policy_id'] ?? 0);
            $policyTableFound = 'leave_policies';

            if ($policyId > 0) {
                // Some environments use different leave policy table names.
                foreach (['attendance_leave_policies', 'leave_policies', 'attendance_policies'] as $policyTable) {
                    if (!Schema::hasTable($policyTable)) {
                        continue;
                    }

                    $select = ['id', 'days_per_year', 'leave_type', 'policy_year_id'];
                    foreach (['name_ar', 'name_en', 'name'] as $col) {
                        if (Schema::hasColumn($policyTable, $col)) {
                            $select[] = $col;
                        }
                    }

                    $policy = DB::table($policyTable)->where('id', $policyId)->first($select);
                    if ($policy) {
                        $policyTableFound = $policyTable;
                        break;
                    }
                }
            }

            $policyNameAr = $policy->name_ar ?? $policy->name ?? null;
            $policyNameEn = $policy->name_en ?? $policy->name ?? null;
            $data['leave_type'] = $policy ? ($isAr ? ($policyNameAr ?: $policyNameEn) : ($policyNameEn ?: $policyNameAr)) : 'Leave';
            $data['requested_days'] = (string) ($data['requested_days'] ?? '0');

            // --- Calculation for Balance and Monthly Stats ---
            $requesterId = (int) ($data[$employeeCol] ?? 0);
            if ($requesterId > 0 && $policy) {
                // 1. Balance Calculation
                $total = (float)($policy->days_per_year ?? 0);
                $consumed = DB::table($src['table'])
                    ->where($employeeCol, $requesterId)
                    ->where('leave_policy_id', $policyId)
                    ->where('status', 'approved')
                    ->sum('requested_days');
                
                $totalStr    = ($total == (int)$total) ? (int)$total : $total;
                $consumedStr = ($consumed == (int)$consumed) ? (int)$consumed : $consumed;
                $totalStr    = ($total == (int)$total) ? (int)$total : $total;
                $data['balance'] = $consumedStr . ' / ' . $totalStr;

                // 2. Monthly Stats
                $currentMonth = now()->format('Y-m');
                $data['monthly_taken_days'] = (float) DB::table($src['table'])
                    ->where($employeeCol, $requesterId)
                    ->where('leave_policy_id', $policyId)
                    ->where('status', 'approved')
                    ->where('start_date', 'like', $currentMonth . '%')
                    ->sum('requested_days');
            } else {
                $data['balance'] = '';
                $data['monthly_taken_days'] = 0;
            }
        }

        // Permission Specifics
        if ($task->approvable_type === 'permissions') {
            $data['leave_type'] = $isAr ? 'إذن' : 'Permission';
            $data['permission_date'] = (string) ($data['date'] ?? $data['permission_date'] ?? '');
            
            $requesterId = (int) ($data[$employeeCol] ?? 0);
            if ($requesterId > 0) {
                $currentMonth = now()->format('Y-m');
                $data['monthly_taken_minutes'] = (int) DB::table($src['table'])
                    ->where($employeeCol, $requesterId)
                    ->where('status', 'approved')
                    ->where('permission_date', 'like', $currentMonth . '%')
                    ->sum('minutes');
            } else {
                $data['monthly_taken_minutes'] = 0;
            }
        }

        // Mission Specifics
        if ($task->approvable_type === 'missions') {
            $data['requested_at'] = (string) ($data['created_at'] ?? '');
            $data['start_date'] = (string) ($data['start_date'] ?? '');
            $data['end_date'] = (string) ($data['end_date'] ?? '');
            $data['type'] = (string) ($data['type'] ?? 'full_day');
            $data['destination'] = (string) ($data['destination'] ?? '');
        }

        return $data;
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
