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

    public function requestSource(string $type): ?array
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
            'missions' => [
                'tables' => [
                    'attendance_mission_requests',
                ],
                'operation_key' => 'missions',
            ],
            'replacements' => [
                'tables' => [
                    'attendance_leave_requests',
                ],
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

    public function summary(Request $request)
    {
        $employeeId = $this->currentEmployeeId();
        if ($employeeId < 1) {
            return response()->json(['ok' => false, 'error' => 'employee_not_found'], 403);
        }

        $companyId = $this->companyId();

        // ✅ NEW: Ensure tasks exist before calculating counts
        foreach (['leaves', 'permissions', 'missions'] as $t) {
            $src = $this->requestSource($t);
            if ($src) {
                $this->ensureTasksForPendingRequests($src);
            }
        }

        // 1. Get counts of pending tasks per operation_key
        $counts = ApprovalTask::query()
            ->where('company_id', $companyId)
            ->where('approver_employee_id', $employeeId)
            ->where('status', 'pending')
            ->selectRaw('operation_key, count(*) as c')
            ->groupBy('operation_key')
            ->pluck('c', 'operation_key')
            ->all();

        // 2. Determine allowed types (even if count is 0)
        // a. Types where they have any task (history included)
        $historicalKeys = ApprovalTask::query()
            ->where('company_id', $companyId)
            ->where('approver_employee_id', $employeeId)
            ->distinct()
            ->pluck('operation_key')
            ->all();

        // b. Types where they are explicitly an approver in policies
        $policyKeys = DB::table('approval_policy_steps')
            ->join('approval_policies', 'approval_policy_steps.policy_id', '=', 'approval_policies.id')
            ->where('approval_policies.company_id', $companyId)
            ->where('approval_policies.is_active', true)
            ->where('approval_policy_steps.approver_type', 'user')
            ->where('approval_policy_steps.approver_id', $employeeId)
            ->distinct()
            ->pluck('approval_policies.operation_key')
            ->all();
        
        // c. Check if they are a manager. If they are, types that use 'direct_manager'
        $isManager = DB::table('employees')->where('manager_id', $employeeId)->exists();
        $managerKeys = [];
        if ($isManager) {
             $managerKeys = DB::table('approval_policy_steps')
                ->join('approval_policies', 'approval_policy_steps.policy_id', '=', 'approval_policies.id')
                ->where('approval_policies.company_id', $companyId)
                ->where('approval_policies.is_active', true)
                ->where('approval_policy_steps.approver_type', 'direct_manager')
                ->distinct()
                ->pluck('approval_policies.operation_key')
                ->all();
        }
        
        // d. Check if they are a replacement in any pending request
        $replacementKeys = [];
        if (Schema::hasTable('attendance_leave_requests') && Schema::hasColumn('attendance_leave_requests', 'replacement_employee_id')) {
            $isReplacement = DB::table('attendance_leave_requests')
                ->where('replacement_employee_id', $employeeId)
                ->where('replacement_status', 'pending')
                ->exists();
            if ($isReplacement) {
                $replacementKeys[] = 'leaves';
            }
        }

        $allowedKeys = array_unique(array_merge($historicalKeys, (array)$policyKeys, (array)$managerKeys, $replacementKeys));

        // Always include types currently supported/defined in the app if they have permission
        $availableTypes = [];
        $supportedTypes = [
            'leaves' => ['key' => 'leaves', 'label_ar' => 'طلبات الإجازات', 'label_en' => 'Leave Requests'],
            'replacements' => ['key' => 'replacements', 'label_ar' => 'تحمل أعباء (بديل)', 'label_en' => 'Replacement Requests'],
            'permissions' => ['key' => 'permissions', 'label_ar' => 'طلبات الأذونات', 'label_en' => 'Permission Requests'],
            'missions' => ['key' => 'missions', 'label_ar' => 'مهام العمل', 'label_en' => 'Work Missions'],
        ];

        foreach ($supportedTypes as $key => $meta) {
            if (in_array($key, $allowedKeys, true)) {
                $availableTypes[] = [
                    'key' => $key,
                    'label_ar' => $meta['label_ar'],
                    'label_en' => $meta['label_en'],
                    'pending_count' => (int) ($counts[$key] ?? 0),
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'data' => $availableTypes,
            'total_pending' => array_sum($counts),
        ]);
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
                    'missions' => $this->requestSource('missions'),
                    'replacements' => $this->requestSource('replacements'),
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
        $status = (string) $request->query('status', 'pending'); // pending|history
        $ensure = (int) $request->query('ensure', 0) === 1;

        $types = $type === 'all' ? ['leaves', 'replacements', 'permissions', 'missions'] : [$type];

        if ($ensure && $status === 'pending') {
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
            ->where('approver_employee_id', $employeeId);

        if ($status === 'pending') {
            $q->where('status', 'pending');
        } else {
            $q->whereIn('status', ['approved', 'rejected']);
        }

        if ($type !== 'all') {
            $q->where('operation_key', $type);
        }

        $p = $q->latest('id')->paginate($perPage);

        // Load request details for each task
        $tasks = $p->items();
        foreach ($tasks as $task) {
            $src = $this->requestSource($task->approvable_type);
            if ($src) {
                $requestData = DB::table($src['table'])->where($src['idCol'], $task->approvable_id)->first();
                if ($requestData) {
                    $locale = $request->header('Accept-Language') ?: 'ar';
                    $isAr = str_contains($locale, 'ar');

                    // Normalize details for UI
                    if ($task->approvable_type === 'leaves') {
                        // Include leave type name if exists with locale support
                        if (isset($requestData->leave_policy_id)) {
                             $policy = DB::table('leave_policies')
                                ->where('id', $requestData->leave_policy_id)
                                ->first(['name']);
                             
                              
                              if ($policy) {
                                 $typeName = $policy->name ?? 'Leave';
                                 if ($task->operation_key === 'replacements') {
                                     $requestData->leave_type = ($isAr ? 'موافقة كبديل (تغطية): ' : 'Replacement for: ') . $typeName;
                                 } else {
                                     $requestData->leave_type = $typeName;
                                 }
                              } else {
                                 $requestData->leave_type = ($task->operation_key === 'replacements') ? ($isAr ? 'موافقة كبديل' : 'Replacement Approval') : 'Leave';
                              }
                        }
                        // Map database dates to mobile app fields
                        $requestData->from_date = $requestData->start_date ?? '';
                        $requestData->to_date = $requestData->end_date ?? '';
                    }
                    
                    if ($task->approvable_type === 'permissions') {
                        $requestData->permission_date = $requestData->permission_date ?? $requestData->start_date ?? '';
                        $requestData->leave_type = $isAr ? 'إذن' : 'Permission';
                    }

                    if ($task->approvable_type === 'missions') {
                        $requestData->leave_type = $isAr ? 'مهمة عمل' : 'Work Mission';
                        $requestData->from_date = $requestData->start_date ?? '';
                        $requestData->to_date = $requestData->end_date ?? $requestData->start_date ?? '';
                        $requestData->requested_at = $requestData->requested_at ?? $requestData->created_at ?? '';
                    }

                    // Common normalization
                    $requestData->request_date = $requestData->requested_at ?? $requestData->created_at ?? '';
                    $requestData->requested_at = $requestData->requested_at ?? $requestData->created_at ?? '';
                    
                    // Attach creator name
                    $creatorId = $this->resolveRequestEmployeeId($src, $requestData);
                    if ($creatorId > 0) {
                        $locale = $request->header('Accept-Language') ?: 'ar';
                        $isAr = str_contains($locale, 'ar');
                        $nameCol = $isAr ? 'name_ar' : 'name_en';
                        
                        $employee = DB::table('employees')
                            ->where('id', $creatorId)
                            ->first(['name_ar', 'name_en']);
                        
                        if ($employee) {
                            $requestData->creator = $employee->{$nameCol} ?: $employee->name_ar ?: $employee->name_en ?: '';
                        }
                    }

                    $task->request = $requestData;
                }
            }
        }

        return response()->json([
            'ok' => true,
            'data' => $tasks,
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

            // ✅ Sync replacement_status if this was a replacement approving
            if ($type === 'leaves' && Schema::hasColumn($src['table'], 'replacement_status')) {
                $req = DB::table($src['table'])->where($src['idCol'], $id)->first();
                if ($req && !empty($req->replacement_employee_id) && $req->replacement_employee_id == $employeeId && $req->replacement_status === 'pending') {
                    DB::table($src['table'])->where($src['idCol'], $id)->update(['replacement_status' => 'approved']);
                }
            }
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

            // ✅ Sync replacement_status if this was a replacement rejecting
            if ($type === 'leaves' && Schema::hasColumn($src['table'], 'replacement_status')) {
                $req = DB::table($src['table'])->where($src['idCol'], $id)->first();
                if ($req && !empty($req->replacement_employee_id) && $req->replacement_employee_id == $employeeId) {
                    DB::table($src['table'])->where($src['idCol'], $id)->update(['replacement_status' => 'rejected']);
                }
            }
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

    public function ensureTasksForPendingRequests(array $src): void
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

    public function ensureTasksForRequest(array $src, int $requestId): void
    {
        $existing = ApprovalTask::query()
            ->where('company_id', $this->companyId())
            ->where('approvable_type', $src['type'])
            ->where('approvable_id', $requestId)
            ->get();

        if ($existing->isNotEmpty()) {
            // ✅ If anyone already approved/rejected, we DON'T touch it.
            $hasAction = $existing->contains(fn($t) => in_array($t->status, ['approved', 'rejected', 'canceled']));
            if ($hasAction) return;

            // ✅ If no action taken yet, we delete and recreate to apply the newest logic (like replacement step)
            ApprovalTask::query()
                ->where('approvable_type', $src['type'])
                ->where('approvable_id', $requestId)
                ->delete();
        }

        $req = DB::table($src['table'])->where($src['idCol'], $requestId)->first();
        if (!$req) return;

        $requestEmployeeId = $this->resolveRequestEmployeeId($src, $req);
        if ($requestEmployeeId < 1) return;

        $allSteps = collect();

        // ✅ NEW: Replacement Step (If it is a leave request and has a replacement employee)
        // This must be the VERY FIRST step.
        if ($src['type'] === 'leaves' && !empty($req->replacement_employee_id) && ($req->replacement_status ?? 'pending') === 'pending') {
            $allSteps->push((object)[
                'approver_type' => 'user',
                'approver_id' => $req->replacement_employee_id,
                '_operation_key' => 'replacements', // 🔥 Clearly separated
            ]);
        }

        if ($src['type'] === 'leaves' && isset($req->is_exception) && $req->is_exception) {
            $exceptionPolicy = $this->resolvePolicyForEmployee('leaves_exceptions', $requestEmployeeId);
            if ($exceptionPolicy) {
                $exceptionSteps = $exceptionPolicy->steps()->orderBy('position')->get();
                foreach ($exceptionSteps as $s) {
                    $s->_operation_key = 'leaves_exceptions';
                    $allSteps->push($s);
                    if ($s->follow_standard) break; // الانتقال للمسار العادي فوراً
                }
            } else {
                $allSteps->push((object)[
                    '_operation_key' => 'leaves_exceptions',
                    'approver_type' => 'user',
                    'approver_id' => null, 
                ]);
            }
        }

        // نضيف المسار العادي لو لم يتم تعبئته أو لو كان هناك استثناء وانتهى بـ follow_standard
        // حالياً في الكود أعلاه، إذا كان استثناء سيمسح كل شيء ويضيف الاستثناءات. 
        // الأفضل جعل المنطق تراكمي:
        $normalPolicy = $this->resolvePolicyForEmployee($src['operation_key'], $requestEmployeeId);
        if ($normalPolicy) {
            $normalSteps = $normalPolicy->steps()->orderBy('position')->get();
            foreach ($normalSteps as $s) {
                $s->_operation_key = $src['operation_key'];
                $allSteps->push($s);
            }
        }

        $firstPendingAssigned = false;
        $globalPosition = 1;

        foreach ($allSteps as $s) {
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
                if ($s->_operation_key === 'leaves_exceptions' && $approverId === 0) {
                    $status = 'waiting';
                }
            }

            if ($status !== 'skipped' && !$firstPendingAssigned) {
                $status = 'pending';
                $firstPendingAssigned = true;
            }

            ApprovalTask::create([
                'company_id' => $this->companyId(),
                'operation_key' => $s->_operation_key ?? $src['operation_key'],
                'approvable_type' => $src['type'],
                'approvable_id' => $requestId,
                'request_employee_id' => $requestEmployeeId,
                'position' => $globalPosition++,
                'approver_employee_id' => $approverId > 0 ? $approverId : null,
                'status' => $status,
                'approver_name' => ($s->_operation_key === 'leaves_exceptions' && $approverId === 0) ? 'Human Resources' : null,
            ]);
        }
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

        // fallback: permissions & missions تستخدم leaves إذا ما لها policy
        if ($policies->count() === 0 && in_array($operationKey, ['permissions', 'missions'], true)) {
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
