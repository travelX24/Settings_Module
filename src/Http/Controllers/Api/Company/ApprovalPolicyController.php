<?php

namespace Athka\SystemSettings\Http\Controllers\Api\Company;

use Athka\SystemSettings\Models\ApprovalPolicy;
use Athka\Employees\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

class ApprovalPolicyController extends Controller
{
    private function companyId(): int
    {
        if (app()->bound('currentCompany') && app('currentCompany')) {
            return (int) app('currentCompany')->id;
        }

        return (int) (Auth::user()->saas_company_id ?? 0);
    }

    private function isRtl(): bool
    {
        $locale = app()->getLocale();
        return in_array(substr($locale, 0, 2), ['ar', 'fa', 'ur', 'he'], true);
    }

    private function labelCandidates(): array
    {
        return $this->isRtl()
            ? ['name_ar', 'name', 'name_en', 'title_ar', 'title', 'title_en']
            : ['name_en', 'name', 'name_ar', 'title_en', 'title', 'title_ar'];
    }

    private function pickLabelColumn(string $table, array $candidates, string $fallback = 'name'): string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) return $col;
        }

        return Schema::hasColumn($table, $fallback) ? $fallback : ($candidates[0] ?? $fallback);
    }

    private function detectCompanyColumn(string $table): ?string
    {
        foreach (['saas_company_id', 'company_id'] as $c) {
            if (Schema::hasColumn($table, $c)) return $c;
        }
        return null;
    }

    private function simpleList(
        string $table,
        string $idCol,
        string $labelCol,
        int $companyId,
        ?string $companyCol = null,
        bool $checkCompanyCol = true
    ): array {
        if (!Schema::hasTable($table)) return [];
        if (!Schema::hasColumn($table, $idCol)) return [];
        if (!Schema::hasColumn($table, $labelCol)) return [];

        $q = DB::table($table)->select([$idCol . ' as id', $labelCol . ' as name']);

        if ($checkCompanyCol) {
            $companyCol = $companyCol ?: $this->detectCompanyColumn($table);
            if ($companyCol && Schema::hasColumn($table, $companyCol)) {
                $q->where($companyCol, $companyId);
            }
        }

        return $q->orderBy($labelCol)
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])
            ->all();
    }

    public function tabs()
    {
        return response()->json([
            'ok'   => true,
            'data' => [
                'leaves'        => tr('Leaves'),
                'overtime'      => tr('Overtime'),
                'compensations' => tr('Compensations'),
                'advances'      => tr('Advances'),
                'terminations'  => tr('Employee Terminations'),
            ],
        ]);
    }

    public function lookups()
    {
        $companyId = $this->companyId();

        $departments = Schema::hasTable('departments')
            ? $this->simpleList('departments', 'id', $this->pickLabelColumn('departments', $this->labelCandidates(), 'name'), $companyId)
            : [];

        $jobTitles = Schema::hasTable('job_titles')
            ? $this->simpleList('job_titles', 'id', $this->pickLabelColumn('job_titles', $this->labelCandidates(), 'name'), $companyId)
            : [];

        $branches = Schema::hasTable('branches')
            ? $this->simpleList('branches', 'id', $this->pickLabelColumn('branches', $this->labelCandidates(), 'name'), $companyId)
            : [];

        $employeesTable = 'employees';
        if (Schema::hasTable('employees')) {
            $employees = $this->simpleList('employees', 'id', $this->pickLabelColumn('employees', $this->labelCandidates(), 'name'), $companyId);
        } elseif (Schema::hasTable('users')) {
            $employeesTable = 'users';
            $employees = $this->simpleList('users', 'id', $this->pickLabelColumn('users', ['name', 'email'], 'name'), $companyId, 'saas_company_id', true);
        } else {
            $employees = [];
        }

        return response()->json([
            'ok'   => true,
            'data' => [
                'departments'   => $departments,
                'job_titles'    => $jobTitles,
                'branches'      => $branches,
                'employees'     => $employees,
                'employeesTable'=> $employeesTable,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId();

        $operationKey = (string) $request->query('operation_key', '');
        $search       = (string) $request->query('search', '');
        $status       = (string) $request->query('status', 'all'); // all|active|inactive
        $perPage      = (int) $request->query('per_page', 10);

        $q = ApprovalPolicy::query()->where('company_id', $companyId);

        if ($operationKey !== '') {
            $q->where('operation_key', $operationKey);
        }

        if ($search !== '') {
            $q->where('name', 'like', '%' . $search . '%');
        }

        if ($status === 'active') {
            $q->where('is_active', true);
        } elseif ($status === 'inactive') {
            $q->where('is_active', false);
        }

        $policies = $q->withCount('steps')
            ->latest('id')
            ->paginate(max(1, min(100, $perPage)));

        $counts = ApprovalPolicy::query()
            ->where('company_id', $companyId)
            ->selectRaw('operation_key, count(*) as c')
            ->groupBy('operation_key')
            ->pluck('c', 'operation_key')
            ->all();

        return response()->json([
            'ok'   => true,
            'data' => $policies->items(),
            'meta' => [
                'current_page' => $policies->currentPage(),
                'last_page'    => $policies->lastPage(),
                'per_page'     => $policies->perPage(),
                'total'        => $policies->total(),
                'counts'       => $counts,
            ],
        ]);
    }

    public function show(int $id)
    {
        $companyId = $this->companyId();

        $policy = ApprovalPolicy::query()
            ->where('company_id', $companyId)
            ->with([
                'scopes:id,policy_id,scope_id',
                'steps:id,policy_id,position,approver_type,approver_id',
            ])
            ->findOrFail($id);

        return response()->json([
            'ok'   => true,
            'data' => [
                'id'           => (int) $policy->id,
                'name'         => (string) $policy->name,
                'is_active'    => (bool) $policy->is_active,
                'operation_key'=> (string) $policy->operation_key,
                'scope_type'   => (string) ($policy->scope_type ?? 'all'),
                'scope_ids'    => $policy->scopes->pluck('scope_id')->map(fn($v)=>(int)$v)->values()->all(),
                'steps'        => $policy->steps
                    ->sortBy('position')
                    ->values()
                    ->map(fn($s)=>[
                        'position'      => (int) $s->position,
                        'approver_type' => (string) $s->approver_type,
                        'approver_id'   => (int) ($s->approver_id ?? 0),
                    ])->all(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        return $this->savePolicy($request, null);
    }

    public function update(Request $request, int $id)
    {
        return $this->savePolicy($request, $id);
    }

    private function savePolicy(Request $request, ?int $id)
    {
        $companyId = $this->companyId();

        $data = $request->all();

        // normalize steps first
        $steps = is_array($data['steps'] ?? null) ? $data['steps'] : [];
        foreach ($steps as $i => $s) {
            $t = (string) ($s['approver_type'] ?? 'direct_manager');
            if ($t === 'direct_manager') {
                $steps[$i]['approver_id'] = 0;
            }
        }
        $data['steps'] = $steps;

        $rules = [
            'operation_key' => ['required', 'string', 'max:60'],
            'name'          => ['required', 'string', 'max:255'],
            'is_active'     => ['boolean'],
            'scope_type'    => ['required', 'in:all,department,job_title,branch,employee'],
            'scope_ids'     => ['array'],
            'scope_ids.*'   => ['integer', 'min:1'],

            'steps'                  => ['required', 'array', 'min:1'],
            'steps.*.approver_type'  => ['required', 'in:direct_manager,user'],
            'steps.*.approver_id'    => ['nullable', 'integer', 'min:0'],
        ];

        if (($data['scope_type'] ?? 'all') !== 'all') {
            $rules['scope_ids'] = ['required', 'array', 'min:1'];
        }

        $validated = $request->validate($rules);

        // custom validation: if user => approver_id must be >= 1
        foreach (($validated['steps'] ?? []) as $i => $s) {
            $t  = (string) ($s['approver_type'] ?? '');
            $aid = (int) ($s['approver_id'] ?? 0);
            if ($t === 'user' && $aid < 1) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'validation_error',
                    'errors'=> [
                        "steps.$i.approver_id" => [tr('This field is required')],
                    ],
                ], 422);
            }
        }

        $editing = null;
        if ($id !== null) {
            $editing = ApprovalPolicy::query()
                ->where('company_id', $companyId)
                ->findOrFail($id);
        }

        $policy = DB::transaction(function () use ($companyId, $validated, $editing) {
            $policy = ApprovalPolicy::updateOrCreate(
                [
                    'id'           => $editing?->id,
                    'company_id'   => $companyId,
                    'operation_key'=> (string) $validated['operation_key'],
                ],
                [
                    'name'       => (string) $validated['name'],
                    'is_active'  => (bool) ($validated['is_active'] ?? true),
                    'scope_type' => (string) ($validated['scope_type'] ?? 'all'),
                    'created_by' => Auth::id(),
                ]
            );

            // scopes replace
            $policy->scopes()->delete();
            if (($validated['scope_type'] ?? 'all') !== 'all') {
                $now = now();
                $rows = collect($validated['scope_ids'] ?? [])
                    ->filter(fn ($sid) => $sid !== null && (string) $sid !== '')
                    ->map(fn ($sid) => [
                        'policy_id'  => (int) $policy->id,
                        'scope_id'   => (int) $sid,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->unique('scope_id')
                    ->values()
                    ->all();

                if (!empty($rows)) {
                    $policy->scopes()->insert($rows);
                }
            }

            // steps replace
            $policy->steps()->delete();
            $pos = 1;
            foreach (($validated['steps'] ?? []) as $s) {
                $type = (string) ($s['approver_type'] ?? 'direct_manager');
                $policy->steps()->create([
                    'position'      => $pos++,
                    'approver_type' => $type,
                    'approver_id'   => ($type === 'direct_manager') ? 0 : (int) ($s['approver_id'] ?? 0),
                ]);
            }

            return $policy;
        });

        return response()->json([
            'ok'   => true,
            'data' => [
                'id' => (int) $policy->id,
            ],
        ]);
    }

    public function destroy(int $id)
    {
        $companyId = $this->companyId();

        $policy = ApprovalPolicy::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        try {
            DB::transaction(function () use ($policy) {
                $policy->steps()->delete();
                $policy->scopes()->delete();
                $policy->delete();
            });

            return response()->json(['ok' => true]);
        } catch (QueryException $e) {
            // لو مربوط بـ FK أو مستخدم: نخليه inactive بدل الحذف
            $policy->update(['is_active' => false]);

            return response()->json([
                'ok'      => true,
                'data'    => ['id' => (int) $policy->id, 'is_active' => false],
                'message' => tr('Policy has been deactivated'),
            ]);
        }
    }

    /**
     * Optional: يرجّع السياسة النشطة المطابقة لموظف حسب scope_type + scope_ids.
     * GET /api/company/approvals/policies/effective?operation_key=leaves&employee_id=123
     */
    public function effective(Request $request)
    {
        $companyId = $this->companyId();

        $request->validate([
            'operation_key' => ['required', 'string', 'max:60'],
            'employee_id'   => ['required', 'integer', 'min:1'],
        ]);

        $operationKey = (string) $request->query('operation_key');
        $employeeId   = (int) $request->query('employee_id');

        // نجيب بيانات الموظف بأكثر شكل آمن
        $employee = null;
        if (class_exists(Employee::class)) {
            $employee = Employee::query()
                ->where('saas_company_id', $companyId)
                ->find($employeeId);
        }

        // fallback DB لو موديل Employee غير متاح
        if (!$employee && Schema::hasTable('employees')) {
            $employee = DB::table('employees')
                ->where($this->detectCompanyColumn('employees') ?? 'saas_company_id', $companyId)
                ->where('id', $employeeId)
                ->first();
        }

        if (!$employee) {
            return response()->json(['ok' => false, 'error' => 'employee_not_found'], 404);
        }

        $deptId   = (int) ($employee->department_id ?? 0);
        $jobId    = (int) ($employee->job_title_id ?? 0);
        $branchId = (int) ($employee->branch_id ?? 0);

        // السياسات النشطة لهذا الـ operation
        $policies = ApprovalPolicy::query()
            ->where('company_id', $companyId)
            ->where('operation_key', $operationKey)
            ->where('is_active', true)
            ->with(['scopes:id,policy_id,scope_id', 'steps:id,policy_id,position,approver_type,approver_id'])
            ->latest('id')
            ->get();

        // اختيار سياسة مطابقة حسب النوع
        $match = $policies->first(function ($p) use ($employeeId, $deptId, $jobId, $branchId) {
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

        if (!$match) {
            return response()->json(['ok' => true, 'data' => null]);
        }

        return response()->json([
            'ok'   => true,
            'data' => [
                'id'           => (int) $match->id,
                'name'         => (string) $match->name,
                'operation_key'=> (string) $match->operation_key,
                'scope_type'   => (string) ($match->scope_type ?? 'all'),
                'scope_ids'    => $match->scopes->pluck('scope_id')->map(fn($v)=>(int)$v)->values()->all(),
                'steps'        => $match->steps->sortBy('position')->values()->map(fn($s)=>[
                    'position'      => (int) $s->position,
                    'approver_type' => (string) $s->approver_type,
                    'approver_id'   => (int) ($s->approver_id ?? 0),
                ])->all(),
            ],
        ]);
    }
}
