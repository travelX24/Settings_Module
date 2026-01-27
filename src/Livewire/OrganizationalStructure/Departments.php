<?php

namespace Athka\SystemSettings\Livewire\OrganizationalStructure;

use Athka\SystemSettings\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Departments extends Component
{
    use WithPagination;

    public $search = '';

    /** ✅ NEW: view mode (list | cards) */
    public string $viewMode = 'table';

    public $showModal = false;
    public $editingId = null;

    // Form fields
    public $name = '';
    public $code = '';
    public $description = '';
    public $manager_id = null;
    public $parent_id = null;
    public $is_active = true;

    // Stats
    public $stats = [];

    protected $paginationTheme = 'tailwind';

    /** ✅ OPTIONAL: keep viewMode in query string */
    protected $queryString = [
        'viewMode' => ['except' => 'table'],
    ];

    public function mount()
    {
        // ✅ Default ALWAYS: List/Table
        $this->viewMode = 'table';
    
        // ✅ Only override if user explicitly provided ?viewMode=
        if (request()->has('viewMode')) {
            $mode = request()->query('viewMode');
            $this->viewMode = in_array($mode, ['table', 'cards'], true) ? $mode : 'table';
        }
    
        $this->loadStats();
    }
    


    public function updatedSearch()
    {
        $this->resetPage();
    }

    /** ✅ NEW: when switching view mode reset pagination */
    public function updatedViewMode()
    {
        $this->resetPage();
    }

    /**
     * Translate + replace placeholders.
     * Example: $this->trp('Department: :name', ['name' => 'Sales'])
     */
    protected function trp(string $english, array $params = [], string $group = 'ui'): string
    {
        $text = tr($english, $group);

        foreach ($params as $key => $value) {
            $text = str_replace(':' . $key, (string) $value, $text);
        }

        return $text;
    }

    public function loadStats()
    {
        $companyId = $this->getCompanyId();

        $totalQuery = Department::forCompany($companyId);
        $total = $totalQuery->count();
        $active = Department::forCompany($companyId)->active()->count();
        $inactive = $total - $active;
        
        $root = Department::forCompany($companyId)->root()->count();
        $subCount = $total - $root;

        // Employee Distribution (Top 3 Departments)
        $distribution = Department::forCompany($companyId)
            ->withCount('employees')
            ->orderBy('employees_count', 'desc')
            ->limit(3)
            ->get()
            ->map(fn($d) => [
                'name' => $d->name,
                'count' => $d->employees_count
            ])->toArray();

        $this->stats = [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'root' => $root,
            'sub' => $subCount,
            'distribution' => $distribution,
        ];
    }

    public function openAddModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $department = Department::forCompany($this->getCompanyId())->findOrFail($id);

        $this->editingId = $department->id;
        $this->name = $department->name;
        $this->code = $department->code ?? '';
        $this->description = $department->description ?? '';
        $this->manager_id = $department->manager_id;
        $this->parent_id = $department->parent_id;
        $this->is_active = $department->is_active;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
        $this->editingId = null;
    }

    public function resetForm()
    {
        $this->name = '';
        $this->code = '';
        $this->description = '';
        $this->manager_id = null;
        $this->parent_id = null;
        $this->is_active = true;
    }

    public function save()
    {
        $companyId = $this->getCompanyId();

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('departments')
                    ->where('saas_company_id', $companyId)
                    ->ignore($this->editingId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:10',
                \Illuminate\Validation\Rule::unique('departments')
                    ->where('saas_company_id', $companyId)
                    ->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['boolean'],
        ];

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'description' => $this->description ?: null,
            'manager_id' => $this->manager_id ?: null,
            'parent_id' => $this->parent_id ?: null,
            'is_active' => (bool) $this->is_active,
            'saas_company_id' => $companyId,
        ];

        if ($this->editingId) {
            $department = Department::forCompany($companyId)->findOrFail($this->editingId);
            $department->update($data);

            session()->flash('status', tr('Department updated successfully'));

            $this->dispatch('toast',
                type: 'success',
                title: tr('Department updated successfully'),
                message: (string) $department->name,
            );
        } else {
            $department = Department::create($data);

            session()->flash('status', tr('Department created successfully'));

            $this->dispatch('toast',
                type: 'success',
                title: tr('Department created successfully'),
                message: (string) $department->name,
            );
        }

        $this->closeModal();
        $this->loadStats();
    }

    public function delete($id)
    {
        $department = Department::forCompany($this->getCompanyId())->findOrFail($id);

        if (! $department->canDelete()) {
            $employeesCount = $department->employees()->count();
            $childrenCount  = $department->children()->count();

            $reasons = [];
            if ($employeesCount > 0) {
                $reasons[] = $this->trp(':count employees', ['count' => $employeesCount]);
            }
            if ($childrenCount > 0) {
                $reasons[] = $this->trp(':count sub-departments', ['count' => $childrenCount]);
            }

            $message = $this->trp(
                'Cannot delete ":name" because it is linked to :reasons.',
                ['name' => $department->name, 'reasons' => implode(' ' . tr('and') . ' ', $reasons)]
            );

            $message .= ' ' . tr('Please transfer employees/sub-departments first or deactivate instead of deleting.');

            $this->dispatch('toast',
                type: 'error',
                title: tr('Deletion Blocked'),
                message: $message,
            );

            return;
        }

        $name = $department->name;
        $department->delete();

        session()->flash('status', tr('Department deleted successfully'));

        $this->dispatch('toast',
            type: 'success',
            title: tr('Department deleted successfully'),
            message: (string) $name,
        );

        $this->loadStats();
        $this->resetPage();
    }

    public function toggleActive($id)
    {
        $department = Department::forCompany($this->getCompanyId())->findOrFail($id);
        $department->update(['is_active' => ! $department->is_active]);

        $title = $department->is_active
            ? tr('Department activated successfully')
            : tr('Department deactivated successfully');

        session()->flash('status', $title);

        $this->dispatch('toast',
            type: 'success',
            title: $title,
            message: (string) $department->name,
        );

        $this->loadStats();
    }

    public function export()
    {
        $companyId = $this->getCompanyId();

        $departments = Department::forCompany($companyId)
            ->with(['manager', 'parent', 'employees'])
            ->get();

        $filename = 'departments_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($departments) {
            $file = fopen('php://output', 'w');

            // BOM for UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Headers
            fputcsv($file, [
                tr('Department Name'),
                tr('Code'),
                tr('Manager'),
                tr('Parent Department'),
                tr('Employees Count'),
                tr('Status'),
            ]);

            // Data
            foreach ($departments as $department) {
                $employeesCount = $department->employees->count();
                if ($department->manager_id && ! $department->employees->contains('id', $department->manager_id)) {
                    $employeesCount++;
                }

                fputcsv($file, [
                    $department->name,
                    $department->code ?? '',
                    $department->manager ? ($department->manager->name_ar ?? $department->manager->name_en) : '',
                    $department->parent ? $department->parent->name : '',
                    $employeesCount,
                    $department->is_active ? tr('Active') : tr('Inactive'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function getCompanyId(): int
    {
        if (app()->bound('currentCompany')) {
            return app('currentCompany')->id;
        }

        return Auth::user()->saas_company_id ?? 0;
    }

    public function render()
    {
        $companyId = $this->getCompanyId();

        $query = Department::forCompany($companyId)
            ->with([
                'manager:id,name_ar,name_en',
                'parent:id,name',
                'employees:id',
            ])
            ->withCount('employees')
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%')
                        ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('name');

        $departments = $query->paginate(15);

        // ✅ نحسب employees_count_display بحيث يشمل المدير لو مش موجود ضمن employees
        // ✅ نحسب employees_count_display بشكل تراكمي (يشمل الأقسام الفرعية) ودون تكرار المدير
        $departments->getCollection()->transform(function ($dept) {
            $allDeptIds = array_merge([$dept->id], $this->getAllDescendantIdsRecursive($dept->id));
            
            // جلب معرّفات الموظفين في هذا القسم وجميع أقسامه الفرعية
            $empIds = \Athka\Employees\Models\Employee::whereIn('department_id', $allDeptIds)
                ->pluck('id')
                ->toArray();
                
            // جلب معرّفات المدراء لهذه الأقسام
            $managerIds = Department::whereIn('id', $allDeptIds)
                ->whereNotNull('manager_id')
                ->pluck('manager_id')
                ->unique()
                ->toArray();
                
            // دمج القائمتين وإزالة التكرار (موظف قد يكون مديراً أيضاً)
            $totalUniqueIds = array_unique(array_merge($empIds, $managerIds));
            
            $dept->employees_count_display = count($totalUniqueIds);

            return $dept;
        });

        // Managers - جلب من جدول الموظفين بدلاً من المستخدمين
        $locale = app()->getLocale();
        $isArabic = in_array(substr($locale, 0, 2), ['ar']);
        
        $managersQuery = \Athka\Employees\Models\Employee::query();
        
        // فقط فلتر بالشركة إذا كان هناك company_id
        if ($companyId > 0) {
            $managersQuery->where('saas_company_id', $companyId);
        }
        
        $managers = $managersQuery
            ->select('id', 'name_ar', 'name_en', 'employee_no')
            ->orderBy($isArabic ? 'name_ar' : 'name_en')
            ->get()
            ->map(function ($employee) use ($isArabic) {
                $name = $isArabic 
                    ? ($employee->name_ar ?: $employee->name_en)
                    : ($employee->name_en ?: $employee->name_ar);
                    
                return [
                    'id' => $employee->id,
                    'name' => $name,
                    'employee_no' => $employee->employee_no,
                ];
            })
            ->values();

        // Parent departments (exclude current when editing)
        $parentDepartmentsQuery = Department::forCompany($companyId)
            ->where('is_active', true);

        if ($this->editingId) {
            $parentDepartmentsQuery->where('id', '!=', $this->editingId);
        }

        if ($this->parent_id) {
            $parentDepartmentsQuery->orWhere('id', $this->parent_id);
        }

        $parentDepartments = $parentDepartmentsQuery
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
            ])
            ->values();

            return view('systemsettings::livewire.organizational-structure.departments', [
            'departments' => $departments,
            'managers' => $managers,
            'parentDepartments' => $parentDepartments,
        ]);
    }

    /**
     * Recursively fetch all descendant department IDs.
     */
    protected function getAllDescendantIdsRecursive($deptId): array
    {
        $ids = [];
        $children = Department::where('parent_id', $deptId)->pluck('id')->toArray();
        
        foreach ($children as $childId) {
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getAllDescendantIdsRecursive($childId));
        }
        
        return $ids;
    }
}





