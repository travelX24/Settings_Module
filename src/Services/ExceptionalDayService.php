<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\AttendanceExceptionalDay;
use Athka\Employees\Support\EmployeeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExceptionalDayService
{
    /**
     * Get the base query for exceptional days with applied filters
     */
    public function getRowsQuery(int $companyId, array $filters): Builder
    {
        $q = AttendanceExceptionalDay::query()->where('company_id', $companyId);

        $today = now()->toDateString();
        $yearStartDate = $filters['yearStartDate'] ?? null;
        $yearEndDate = $filters['yearEndDate'] ?? null;
        $monthStartDate = $filters['monthStartDate'] ?? null;
        $monthEndDate = $filters['monthEndDate'] ?? null;
        $dateFrom = $filters['dateFrom'] ?? null;
        $dateTo = $filters['dateTo'] ?? null;
        $search = $filters['search'] ?? '';
        $status = $filters['status'] ?? 'all';
        $deductionType = $filters['deductionType'] ?? 'all';
        $departmentId = $filters['departmentId'] ?? null;
        $branchId = $filters['branchId'] ?? null;
        $contractType = $filters['contractType'] ?? null;

        $minPercent = ($filters['minMultiplier'] ?? null) !== null ? (float) $filters['minMultiplier'] : null;
        $maxPercent = ($filters['maxMultiplier'] ?? null) !== null ? (float) $filters['maxMultiplier'] : null;

        $minFactor = ($minPercent !== null) ? ($minPercent / 100.0) : null;
        $maxFactor = ($maxPercent !== null) ? ($maxPercent / 100.0) : null;

        $q->when($yearStartDate && $yearEndDate, function ($qq) use ($yearStartDate, $yearEndDate) {
            $qq->whereBetween('start_date', [$yearStartDate, $yearEndDate]);
        })
            ->when($monthStartDate && $monthEndDate, function ($qq) use ($monthStartDate, $monthEndDate) {
                $qq->whereBetween('start_date', [$monthStartDate, $monthEndDate]);
            })
            ->when($dateFrom || $dateTo, function ($qq) use ($dateFrom, $dateTo) {
                $from = $dateFrom ?: '0001-01-01';
                $to = $dateTo ?: '9999-12-31';

                $qq->where(function ($q2) use ($from, $to) {
                    $q2->whereDate('start_date', '<=', $to)
                        ->where(function ($q3) use ($from) {
                            $q3->whereDate('end_date', '>=', $from)
                                ->orWhere(function ($q4) use ($from) {
                                    $q4->whereNull('end_date')
                                        ->whereDate('start_date', '>=', $from);
                                });
                        });
                });
            })
            ->when($search !== '', function ($qq) use ($search) {
                $s = trim($search);
                $qq->where(function ($q2) use ($s) {
                    $q2->where('name', 'like', "%{$s}%")
                        ->orWhere('description', 'like', "%{$s}%");
                });
            })
            ->when($status !== 'all', function ($qq) use ($today, $status) {
                if ($status === 'current') {
                    $qq->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>=', $today);
                } elseif ($status === 'upcoming') {
                    $qq->whereDate('start_date', '>', $today);
                } elseif ($status === 'ended') {
                    $qq->whereDate('end_date', '<', $today);
                }
            })
            ->when($deductionType !== 'all', function ($qq) use ($deductionType) {
                $type = (string) $deductionType;
                if (in_array($type, ['absence', 'late'], true)) {
                    $qq->where('apply_on', $type);
                    return;
                }
                if ($type === 'without') {
                    $qq->where(function ($w) {
                        $w->orWhere('apply_on', 'none')
                            ->orWhere(function ($a) {
                                $a->where('apply_on', 'absence')->where('absence_multiplier', '<=', 0);
                            })
                            ->orWhere(function ($l) {
                                $l->where('apply_on', 'late')->where('late_multiplier', '<=', 0);
                            });
                    });
                }
            })
            ->when($minFactor !== null && $minFactor !== 0.0, function ($qq) use ($minFactor) {
                $qq->where(function ($w) use ($minFactor) {
                    $w->where(function ($a) use ($minFactor) {
                        $a->where('apply_on', 'absence')->where('absence_multiplier', '>=', $minFactor);
                    })->orWhere(function ($l) use ($minFactor) {
                        $l->where('apply_on', 'late')->where('late_multiplier', '>=', $minFactor);
                    });
                });
            })
            ->when($maxFactor !== null && $maxFactor !== 0.0, function ($qq) use ($maxFactor) {
                $qq->where(function ($w) use ($maxFactor) {
                    $w->where(function ($a) use ($maxFactor) {
                        $a->where('apply_on', 'absence')->where('absence_multiplier', '<=', $maxFactor);
                    })->orWhere(function ($l) use ($maxFactor) {
                        $l->where('apply_on', 'late')->where('late_multiplier', '<=', $maxFactor);
                    })->orWhere(function ($n) {
                        $n->where('apply_on', 'none');
                    });
                });
            })
            ->when($departmentId, function ($qq) use ($departmentId) {
                $deptIdInt = (int) $departmentId;
                $deptIdStr = (string) $departmentId;

                $qq->where('scope_type', 'departments')
                    ->where(function ($q2) use ($deptIdInt, $deptIdStr) {
                        $q2->whereJsonContains('include->departments', $deptIdStr)
                            ->orWhereJsonContains('include->departments', $deptIdInt);
                    });
            })
            ->when($branchId, function ($qq) use ($branchId) {
                $branchIdInt = (int) $branchId;
                $branchIdStr = (string) $branchId;

                $qq->where('scope_type', 'branches')
                    ->where(function ($q2) use ($branchIdInt, $branchIdStr) {
                        $q2->whereJsonContains('include->branches', $branchIdStr)
                            ->orWhereJsonContains('include->branches', $branchIdInt);
                    });
            })
            ->when($contractType, function ($qq) use ($contractType) {
                $qq->where(function ($q2) use ($contractType) {
                    $q2->where('scope_type', 'all')
                        ->orWhere(function ($q3) use ($contractType) {
                            $q3->where('scope_type', 'contract_types')
                                ->whereJsonContains('include->contract_types', $contractType);
                        });
                });
            })
            ->orderBy('start_date', 'desc');

        return $q;
    }

    /**
     * Check if a date range overlaps with an exceptional day targeting any of the same employees
     */
    public function checkOverlap(
        int $companyId,
        string $start,
        string $end,
        ?int $ignoreId = null,
        string $scopeType = 'all',
        array $include = [],
        ?string $applyOn = null
    ): bool {
        $applyOnVal = $applyOn ?: 'absence';

        $rows = AttendanceExceptionalDay::query()
            ->where('company_id', $companyId)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->when(
                $applyOnVal === 'absence',
                fn ($q) => $q->where(function ($applyOnQuery) {
                    $applyOnQuery->where('apply_on', 'absence')
                        ->orWhereNull('apply_on');
                }),
                fn ($q) => $q->where('apply_on', $applyOnVal)
            )
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->get(['id', 'apply_on', 'scope_type', 'include']);

        $incomingScopeType = $scopeType ?: 'all';
        $incomingInclude = $this->normalizeScopeInclude($include);

        foreach ($rows as $row) {
            $existingScopeType = (string) ($row->scope_type ?: 'all');
            $existingInclude = $this->normalizeScopeInclude((array) ($row->include ?? []));

            if ($this->scopesConflict($companyId, $existingScopeType, $existingInclude, $incomingScopeType, $incomingInclude)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the active financial Exceptional Day for one employee and one
     * concrete violation. Different violation types may coexist on the same
     * date, so the generic calendar resolver must not decide financial priority.
     */
    public function findApplicableForEmployeeViolation(
        int $companyId,
        string $date,
        int $employeeId,
        string $applyOn
    ): ?AttendanceExceptionalDay {
        if (!in_array($applyOn, ['absence', 'late'], true)) {
            return null;
        }

        if ($companyId <= 0 || $employeeId <= 0) {
            return null;
        }

        $rows = AttendanceExceptionalDay::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('apply_on', $applyOn)
            ->whereDate('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereDate('end_date', '>=', $date)
                    ->orWhere(function ($nested) use ($date) {
                        $nested->whereNull('end_date')
                            ->whereDate('start_date', '<=', $date);
                    });
            })
            ->orderByDesc('id')
            ->get();

        foreach ($rows as $row) {
            $include = $this->normalizeScopeInclude((array) ($row->include ?? []));
            $scopeType = $this->normalizeScopeType(
                (string) ($row->scope_type ?: 'all'),
                $include
            );

            $matchesEmployee = $this->scopeEmployeeQuery(
                $companyId,
                $scopeType,
                $include
            )
                ->where('id', $employeeId)
                ->exists();

            if (! $matchesEmployee) {
                continue;
            }

            $exclude = (array) ($row->exclude ?? []);
            $excludedEmployeeIds = array_values(array_unique(array_map(
                'intval',
                $exclude['employees'] ?? []
            )));

            if (in_array($employeeId, $excludedEmployeeIds, true)) {
                continue;
            }

            return $row;
        }

        return null;
    }

    private function normalizeScopeInclude(array $include): array
    {
        return [
            'departments' => array_values(array_unique(array_map('strval', $include['departments'] ?? []))),
            'sections' => array_values(array_unique(array_map('strval', $include['sections'] ?? []))),
            'branches' => array_values(array_unique(array_map('strval', $include['branches'] ?? []))),
            'contract_types' => array_values(array_unique(array_map('strval', $include['contract_types'] ?? []))),
            'employees' => array_values(array_unique(array_map('strval', $include['employees'] ?? []))),
        ];
    }

    private function scopesConflict(
        int $companyId,
        string $existingType,
        array $existingInclude,
        string $incomingType,
        array $incomingInclude
    ): bool {
        $existingType = $this->normalizeScopeType($existingType, $existingInclude);
        $incomingType = $this->normalizeScopeType($incomingType, $incomingInclude);

        /*
         * Resolve both scopes to the employees they actually target.
         * This catches cross-scope conflicts such as:
         * employee <-> department, employee <-> branch,
         * department <-> branch, branch <-> contract type, etc.
         */
        if (Schema::hasTable('employees')) {
            $existingEmployeeIds = $this->scopeEmployeeQuery(
                $companyId,
                $existingType,
                $existingInclude
            )->select('id');

            return $this->scopeEmployeeQuery(
                $companyId,
                $incomingType,
                $incomingInclude
            )
                ->whereIn('id', $existingEmployeeIds)
                ->exists();
        }

        /*
         * Safe compatibility fallback for installations where the
         * employees table is not available.
         */
        return $this->legacyScopesConflict(
            $existingType,
            $existingInclude,
            $incomingType,
            $incomingInclude
        );
    }

    private function scopeEmployeeQuery(
        int $companyId,
        string $scopeType,
        array $include
    ) {
        $query = DB::table('employees');

        $companyColumn = $this->companyColumnFor('employees');

        if (!$companyColumn) {
            return $query->whereRaw('1 = 0');
        }

        $query->where($companyColumn, $companyId);

        if ($scopeType === 'all') {
            return $query;
        }

        if ($scopeType === 'employees') {
            $employeeIds = $this->positiveIntegerValues($include['employees'] ?? []);

            return empty($employeeIds)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('id', $employeeIds);
        }

        if ($scopeType === 'branches') {
            $branchIds = $this->positiveIntegerValues($include['branches'] ?? []);

            return empty($branchIds)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('branch_id', $branchIds);
        }

        if ($scopeType === 'contract_types') {
            $contractTypes = $this->nonEmptyStringValues($include['contract_types'] ?? []);

            return empty($contractTypes)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('contract_type', $contractTypes);
        }

        if ($scopeType === 'departments') {
            $departmentIds = $this->positiveIntegerValues($include['departments'] ?? []);
            $sectionIds = $this->positiveIntegerValues($include['sections'] ?? []);

            if (empty($departmentIds) && empty($sectionIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function ($q) use ($departmentIds, $sectionIds) {
                if (!empty($departmentIds)) {
                    $q->whereIn('department_id', $departmentIds);
                }

                if (!empty($sectionIds)) {
                    if (!empty($departmentIds)) {
                        $q->orWhereIn('sub_department_id', $sectionIds);
                    } else {
                        $q->whereIn('sub_department_id', $sectionIds);
                    }
                }
            });
        }

        return $query->whereRaw('1 = 0');
    }

    private function normalizeScopeType(string $scopeType, array $include): string
    {
        if (in_array($scopeType, ['all', 'departments', 'branches', 'contract_types', 'employees'], true)) {
            return $scopeType;
        }

        if (!empty($include['employees'])) {
            return 'employees';
        }

        if (!empty($include['branches'])) {
            return 'branches';
        }

        if (!empty($include['contract_types'])) {
            return 'contract_types';
        }

        if (!empty($include['departments']) || !empty($include['sections'])) {
            return 'departments';
        }

        return 'all';
    }

    private function positiveIntegerValues(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $values),
            fn ($value) => $value > 0
        )));
    }

    private function nonEmptyStringValues(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($value) => trim((string) $value), $values),
            fn ($value) => $value !== ''
        )));
    }

    private function legacyScopesConflict(
        string $existingType,
        array $existingInclude,
        string $incomingType,
        array $incomingInclude
    ): bool {
        if ($existingType === 'all' || $incomingType === 'all') {
            return true;
        }

        if ($existingType !== $incomingType) {
            return false;
        }

        return match ($existingType) {
            'departments' => $this->hasIntersection(
                array_merge($existingInclude['departments'], $existingInclude['sections']),
                array_merge($incomingInclude['departments'], $incomingInclude['sections'])
            ),
            'branches' => $this->hasIntersection(
                $existingInclude['branches'],
                $incomingInclude['branches']
            ),
            'contract_types' => $this->hasIntersection(
                $existingInclude['contract_types'],
                $incomingInclude['contract_types']
            ),
            'employees' => $this->hasIntersection(
                $existingInclude['employees'],
                $incomingInclude['employees']
            ),
            default => true,
        };
    }

    private function hasIntersection(array $left, array $right): bool
    {
        return !empty(array_intersect($left, $right));
    }

    /**
     * Resolve the branches the current user is allowed to access.
     *
     * null  = unrestricted / all branches
     * array = only these branch IDs
     */
    public function currentUserAllowedBranchIds(int $companyId): ?array
    {
        $user = auth()->user();

        if (!$user) {
            return [];
        }

        /*
         * Prefer the application's canonical branch restriction helper.
         */
        if (method_exists($user, 'restrictedBranchIds')) {
            $restricted = $user->restrictedBranchIds();

            if ($restricted === null) {
                return null;
            }

            return collect($restricted)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        /*
         * Administrative roles are unrestricted.
         */
        if (
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole([
                'saas-admin',
                'super-admin',
                'company-admin',
                'system-admin',
            ])
        ) {
            return null;
        }

        $scope = (string) ($user->access_scope ?? 'all_branches');

        if ($scope === 'all_branches') {
            return null;
        }

        /*
         * Own branch only.
         */
        if (in_array($scope, ['my_branch', 'branch'], true)) {
            $branchId = 0;

            if (
                !empty($user->employee_id)
                && Schema::hasTable('employees')
            ) {
                $branchCol = $this->employeeBranchColumn();

                if ($branchCol) {
                    $query = DB::table('employees')
                        ->where('id', (int) $user->employee_id);

                    $companyCol = $this->companyColumnFor('employees');

                    if ($companyCol) {
                        $query->where($companyCol, $companyId);
                    }

                    $branchId = (int) ($query->value($branchCol) ?? 0);
                }
            }

            if ($branchId <= 0) {
                $branchId = (int) ($user->branch_id ?? 0);
            }

            return $branchId > 0 ? [$branchId] : [];
        }

        /*
         * Selected/custom branches.
         */
        if (!Schema::hasTable('branch_user_access')) {
            return [];
        }

        $query = DB::table('branch_user_access')
            ->where('user_id', (int) $user->id);

        if (Schema::hasColumn('branch_user_access', 'saas_company_id')) {
            $query->where('saas_company_id', $companyId);
        } elseif (Schema::hasColumn('branch_user_access', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Load scope options (departments, employees, etc) for dropdowns
     */
    public function loadScopeOptions(int $companyId, string $locale = 'en', ?array $allowedBranchIds = null, ?array $parentDeptIds = null, string $employeeStatus = EmployeeStatus::ACTIVE): array
    {
        $departments = [];
        $sections = [];
        $employees = [];
        $branches = [];
        $contractTypes = [];

        $isArabic = $locale === 'ar';

        if (Schema::hasTable('departments')) {
            $companyCol = $this->companyColumnFor('departments');
            $nameExpr = $this->coalesceNameExpr('departments', $isArabic ? ['name_ar', 'name', 'name_en'] : ['name_en', 'name', 'name_ar']);
            
            // Main Departments (No parent)
            $departments = DB::table('departments')
                ->where('is_active', 1)
                ->whereNull('parent_id')
                ->when($companyCol, fn($q) => $q->where($companyCol, $companyId))
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->get()->toArray();

            // Sections/Sub-departments (Have parent)
            $sections = DB::table('departments')
                ->where('is_active', 1)
                ->whereNotNull('parent_id')
                ->when($parentDeptIds, fn($q) => $q->whereIn('parent_id', $parentDeptIds))
                ->when($companyCol, fn($q) => $q->where($companyCol, $companyId))
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->get()->toArray();
        }

        if (Schema::hasTable('employees')) {
            $companyCol = $this->companyColumnFor('employees');
            $nameExpr = $this->coalesceNameExpr('employees', $isArabic ? ['name_ar', 'name', 'full_name', 'name_en', 'employee_no'] : ['name_en', 'name', 'full_name', 'name_ar', 'employee_no']);
            $branchCol = $this->employeeBranchColumn();

            $employees = DB::table('employees')
                ->when($employeeStatus !== 'all', fn ($q) => $q->where('status', $employeeStatus))
                ->when($companyCol, fn($q) => $q->where($companyCol, $companyId))
                ->when($allowedBranchIds !== null, function ($q) use ($allowedBranchIds, $branchCol) {
                    if (!$branchCol) {
                        $q->whereRaw('1=0');
                        return;
                    }
                    $q->whereIn($branchCol, $allowedBranchIds);
                })
                ->select('id', 'status', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->limit(300)
                ->get()
                ->map(function ($employee) {
                    if (($employee->status ?? EmployeeStatus::ACTIVE) !== EmployeeStatus::ACTIVE) {
                        $employee->name .= ' - ' . EmployeeStatus::label($employee->status);
                    }

                    return $employee;
                })
                ->toArray();

            $contractTypes = DB::table('employees')
                ->where('saas_company_id', $companyId)
                ->whereNotNull('contract_type')
                ->where('contract_type', '!=', '')
                ->distinct()
                ->pluck('contract_type')
                ->map(fn($t) => (object) ['id' => $t, 'name' => $t])
                ->toArray();
        }

        if (Schema::hasTable('branches')) {
            $companyCol = $this->companyColumnFor('branches');
            $nameExpr = $this->coalesceNameExpr('branches', $isArabic ? ['name_ar', 'name', 'name_en'] : ['name_en', 'name', 'name_ar']);

            $branches = DB::table('branches')
                ->when($companyCol, fn($q) => $q->where($companyCol, $companyId))
                ->when($allowedBranchIds !== null, fn($q) => $q->whereIn('id', $allowedBranchIds))
                ->select('id', DB::raw("{$nameExpr} as name"))
                ->orderByRaw("{$nameExpr} asc")
                ->get()->toArray();
        }

        return [
            'departments' => $departments,
            'sections' => $sections,
            'employees' => $employees,
            'branches' => $branches,
            'contract_types' => $contractTypes,
        ];
    }

    private function companyColumnFor(string $table): ?string
    {
        if (Schema::hasColumn($table, 'saas_company_id')) return 'saas_company_id';
        if (Schema::hasColumn($table, 'company_id')) return 'company_id';
        return null;
    }

    private function employeeBranchColumn(): ?string
    {
        if (Schema::hasColumn('employees', 'branch_id')) return 'branch_id';
        if (Schema::hasColumn('employees', 'primary_branch_id')) return 'primary_branch_id';
        return null;
    }

    private function coalesceNameExpr(string $table, array $columns): string
    {
        $existing = array_filter($columns, fn($col) => Schema::hasColumn($table, $col));
        if (empty($existing)) {
            return "'N/A'";
        }
        return 'COALESCE(' . implode(', ', $existing) . ')';
    }
}
