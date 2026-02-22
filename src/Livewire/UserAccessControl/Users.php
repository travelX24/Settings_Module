<?php

namespace Athka\SystemSettings\Livewire\UserAccessControl;

use App\Models\User;
use Athka\Employees\Models\Employee;
use Spatie\Permission\Models\Role;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
class Users extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $showPasswordModal = false;
    public $editingId = null;

    // Search Step
    public $employeeSearch = '';
    public $selectedEmployeeId = null;
    public $foundEmployees = [];

    // Form Fields
    public $name = '';
    public $email = '';
    public $role = '';
    public $access_scope = 'my_branch'; 
    public array $allowed_branch_ids = []; 
    public $is_active = true;
    public $password = ''; // Only used if manual setting is ever needed, but requirement says send email

    // Display fields from Employee
    public $display_name = '';
    public $display_phone = '';
    public $display_branch = '';
    public $display_department = '';
    public $display_job_title = '';

    public $permissionGroups = [];
    public $permissionsMap = [];

    // ✅ Branch filtering
    public array $branches = [];
    public array $branchesById = [];
    public string $filterBranchId = ''; // '' => all
    public ?string $employeeBranchCol = null; // usually 'branch_id'
    public bool $lockBranchFilter = false; // if current user is my_branch
    // Form Fields
    public string $access_type = 'system_and_app'; // ✅ system_and_app | hr_app_only
    protected $listeners = [
        'refreshUsers' => '$refresh',
        'open-add-user-modal' => 'openAddModal',
    ];

    public function mount()
    {
        $this->loadPermissionGroups();
        $this->loadBranches();
        $this->initBranchFilterLock();
    }
    private function loadBranches(): void
    {
        $this->branches = [];
        $this->branchesById = [];

        $this->employeeBranchCol = (Schema::hasTable('employees') && Schema::hasColumn('employees', 'branch_id'))
            ? 'branch_id'
            : null;

        if (!Schema::hasTable('branches')) {
            return;
        }

        $companyId = $this->getCompanyId();

        // Prefer Arabic on RTL
        $labelCol = 'name';
        $locale = app()->getLocale();
        $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);

        if ($isRtl && Schema::hasColumn('branches', 'name_ar')) {
            $labelCol = 'name_ar';
        } elseif (!$isRtl && Schema::hasColumn('branches', 'name_en')) {
            $labelCol = 'name_en';
        } elseif (!Schema::hasColumn('branches', $labelCol)) {
            // fallback
            $labelCol = Schema::hasColumn('branches', 'title') ? 'title' : 'id';
        }

        // detect company col
        $companyCol = null;
        foreach (['saas_company_id', 'company_id'] as $c) {
            if (Schema::hasColumn('branches', $c)) { $companyCol = $c; break; }
        }

        $q = DB::table('branches')->select(['id', DB::raw("$labelCol as name")]);

        if ($companyCol) {
            $q->where($companyCol, $companyId);
        }

        $rows = $q->orderBy($labelCol)->get();

        $this->branches = $rows->map(fn($r) => ['id' => (int)$r->id, 'name' => (string)$r->name])->all();
        $this->branchesById = collect($this->branches)->pluck('name', 'id')->all();
    }

    private function currentUserBranchId(): ?int
    {
        if (!$this->employeeBranchCol) return null;

        $employeeId = Auth::user()?->employee_id;
        if (!$employeeId) return null;

        $bid = DB::table('employees')->where('id', $employeeId)->value($this->employeeBranchCol);

        return $bid ? (int)$bid : null;
    }

    private function initBranchFilterLock(): void
    {
        $scope = Auth::user()?->access_scope ?? 'all_branches';

        if ($scope === 'my_branch') {
            $this->lockBranchFilter = true;
            $bid = $this->currentUserBranchId();
            $this->filterBranchId = $bid ? (string)$bid : '';
        }
    }

    private function effectiveBranchId(): ?int
    {
        if (($this->filterBranchId ?? '') === '') return null;
        return (int)$this->filterBranchId;
    }

    public function updatedFilterBranchId(): void
    {
        if ($this->lockBranchFilter) {
            $bid = $this->currentUserBranchId();
            $this->filterBranchId = $bid ? (string)$bid : '';
            return;
        }

        $this->resetPage();
    }
    public function loadPermissionGroups()
    {
        $this->permissionGroups = [
            'Dashboard' => [
                'dashboard.view' => 'View Dashboard Statistics',
                'dashboard.reports' => 'Access Reports Dashboard',
            ],
            'Employee Management' => [
                'employees.view' => 'View Employees List',
                'employees.create' => 'Add New Employee',
                'employees.edit' => 'Edit Employee Details',
                'employees.delete' => 'Delete Employee',
                'employees.export' => 'Export Employee Data',
                'employees.documents.manage' => 'Manage Employee Documents',
            ],
            'Attendance & Shifts' => [
                'attendance.view' => 'View Daily Attendance',
                'attendance.manage' => 'Manage Manual Entry & Corrections',
                'shifts.view' => 'View Shifts Schedule',
                'shifts.manage' => 'Manage Shifts & Rotation Rules',
                'holidays.manage' => 'Manage Official Holidays',
            ],
            'System Settings' => [
                'settings.general.view' => 'View General Settings',
                'settings.general.edit' => 'Edit General Settings',
                'settings.organizational.view' => 'View Organizational Structure',
                'settings.organizational.manage' => 'Manage Departments & Job Titles',
                'settings.attendance.view' => 'View Attendance Settings',
                'settings.attendance.manage' => 'Manage Shifts & Rules',
            ],
            'Locations & Geofencing' => [
                'locations.view' => 'View Company Locations',
                'locations.manage' => 'Add/Edit Working Locations',
                'geofencing.manage' => 'Manage Geofencing Rules',
            ],
            'User Access Control' => [
                'uac.users.view' => 'View System Users',
                'uac.users.manage' => 'Create/Edit System Users',
                'uac.roles.view' => 'View Roles & Permissions',
                'uac.roles.manage' => 'Create/Edit Roles & Permissions',
            ],
            'System Management' => [
                'settings.approval.manage' => 'Manage Approval Workflows',
                'settings.lists.manage' => 'Manage System Lists',
                'settings.currencies.manage' => 'Manage Currencies',
                'settings.calendar.manage' => 'Manage Calendar & Working Days',
            ],
            'Data & Branding' => [
                'settings.branding.view' => 'View Branding Settings',
                'settings.branding.manage' => 'Manage Logo & Themes',
                'settings.backup.view' => 'View Backup Records',
                'settings.backup.manage' => 'Perform System Backups',
            ],
            'Activity Logs' => [
                'logs.view' => 'View System Activity Logs',
                'logs.export' => 'Export Activity Logs',
            ],
        ];

        $this->permissionsMap = [];
        foreach ($this->permissionGroups as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                $this->permissionsMap[$name] = $label;
            }
        }
    }

    public function updatedEmployeeSearch()
    {
        if (strlen($this->employeeSearch) < 2) {
            $this->foundEmployees = [];
            return;
        }

        $takenEmployeeIds = User::where('saas_company_id', $this->getCompanyId())
            ->whereNotNull('employee_id')
            ->pluck('employee_id')
            ->toArray();

         $this->foundEmployees = Employee::forCompany($this->getCompanyId())
             ->with(['jobTitle', 'department']) 
            ->where(function($q) {
                $q->where('name_ar', 'like', '%' . $this->employeeSearch . '%')
                  ->orWhere('name_en', 'like', '%' . $this->employeeSearch . '%')
                  ->orWhere('employee_no', 'like', '%' . $this->employeeSearch . '%');
            })
            ->whereNotIn('id', $takenEmployeeIds)
            ->take(5)
            ->get();
    }

    public function selectEmployee($employeeId)
    {
        $employee = Employee::with(['department', 'jobTitle'])->find($employeeId);
        if (!$employee) return;

        // Check if this is the primary admin account
        $firstUser = User::where('saas_company_id', $this->getCompanyId())->orderBy('id', 'asc')->first();
        if ($this->editingId && (int)$this->editingId === (int)$firstUser->id) {
             // For the primary admin, the work email MUST match the user email (current value in the input field)
             if (strtolower(trim($employee->email_work)) !== strtolower(trim($this->email))) {
                 $message = str_replace(':email', $this->email, tr('For the primary admin account, the linked employee work email must be the same as the user email (:email).'));
                 $this->dispatch('toast', type: 'error', message: $message);
                 return;
             }
        }

        $this->selectedEmployeeId = $employee->id;
        
        // Auto-fill form
        $this->name = $employee->email_work ? strstr($employee->email_work, '@', true) : str_replace(' ', '.', strtolower($employee->name_en ?? 'user'));
        $this->email = $employee->email_work ?? $employee->email_personal ?? '';
        
        // Display fields
        $this->display_name = $employee->name_ar . ' / ' . $employee->name_en;
        $this->display_phone = $employee->mobile;
        
        // Branch/Department logic
        // Assuming Company is the branch context or we look at Sector/Department
        $empBranchId = (int) ($employee->branch_id ?? 0);
        $this->display_branch = $empBranchId && isset($this->branchesById[$empBranchId])
            ? $this->branchesById[$empBranchId]
            : '—';

        if ($this->access_scope === 'my_branch' && $empBranchId > 0) {
            $this->allowed_branch_ids = [$empBranchId];
        }
        $this->display_department = $employee->department->name ?? '-';
        $this->display_job_title = $employee->jobTitle->name ?? '-';

        $this->foundEmployees = [];
    }

    public function openAddModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public $needs_employee_link = false;
    public $is_locked_role = false;

    // ...

    public function openEditModal($id)
    {
        $user = User::where('saas_company_id', $this->getCompanyId())->findOrFail($id);
        
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->access_scope = $user->access_scope ?? 'my_branch';
        $this->access_type  = $user->access_type ?? 'system_and_app';
        $this->is_active = $user->is_active ?? true;

                
        // Check if user needs employee link (e.g. company admin created initially)
        $this->needs_employee_link = is_null($user->employee_id);

        // Load Role
        $roleName = $user->roles->first()?->name ?? '';
        $this->role = $roleName;

        // Lock role ONLY if it's the primary account (the first user created for the company)
        $firstUser = User::where('saas_company_id', $this->getCompanyId())->orderBy('id', 'asc')->first();
        $this->is_locked_role = ($id === $firstUser->id);

        if ($this->is_locked_role) {
            $this->access_type = 'system_and_app';
        }
        $this->allowed_branch_ids = [];

        if (($user->access_scope ?? 'all_branches') === 'selected_branches') {
            if (method_exists($user, 'allowedBranches')) {
                $this->allowed_branch_ids = $user->allowedBranches()->pluck('branches.id')->map(fn($v) => (int)$v)->all();
            }
        } elseif (($user->access_scope ?? '') === 'my_branch') {
            $bid = (int) ($user->employee?->branch_id ?? 0);
            if ($bid > 0) $this->allowed_branch_ids = [$bid];
        }

        $this->showModal = true;
    }

    public function save()
    {
        $companyId = $this->getCompanyId();
        
        $this->validate([
            'name' => [
                'required', 
                'string', 
                'max:255',
                Rule::unique('users')->where('saas_company_id', $companyId)->ignore($this->editingId)
            ],
            'email' => [
                'required', 
                'email', 
                Rule::unique('users')->ignore($this->editingId)
            ],
           'access_type' => ['required', 'in:system_and_app,hr_app_only'],

            'role' => $this->is_locked_role
                ? 'nullable'
                : ($this->access_type === 'system_and_app'
                    ? ['required', 'exists:roles,name']
                    : ['nullable']),

            'access_scope' => ['required', 'in:my_branch,all_branches,selected_branches'],

            'allowed_branch_ids' => $this->access_scope === 'selected_branches'
                ? ['required', 'array', 'min:1']
                : ['nullable', 'array'],

            'allowed_branch_ids.*' => [
                'integer',
                Rule::exists('branches', 'id')->where('saas_company_id', $companyId),
            ],
        ], [
            'name.required' => tr('The username field is required.'),
            'name.unique' => tr('The username is already taken.'),
            'email.required' => tr('The email field is required.'),
            'email.email' => tr('Please enter a valid email address.'),
            'email.unique' => tr('The email address is already taken.'),
            'role.required' => tr('Please select a role for the user.'),
            'access_scope.required' => tr('Please select the access scope.'),
        ]);

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            
          $updateData = [
                'email' => $this->email,
                'access_scope' => $this->access_scope,
                'access_type'  => $this->access_type,
                'is_active' => $this->is_active
            ];


            // If we are linking an employee for the first time (e.g. for company-admin)
            if ($this->needs_employee_link && $this->selectedEmployeeId) {
                // Primary account integrity check: 
                // If this is the FIRST user (Primary Admin), the employee's work email MUST match the user login email.
                $firstUser = User::where('saas_company_id', $this->getCompanyId())->orderBy('id', 'asc')->first();
                if ((int)$user->id === (int)$firstUser->id) {
                    $employee = Employee::find($this->selectedEmployeeId);
                    if ($employee && strtolower(trim($employee->email_work)) !== strtolower(trim($this->email))) {
                        $message = str_replace(':email', $this->email, tr('For the primary admin account, the linked employee work email must be the same as the user email (:email).'));
                        $this->dispatch('toast', type: 'error', message: $message);
                        return;
                    }
                }

                $updateData['employee_id'] = $this->selectedEmployeeId;
            }

            $user->update($updateData);

            // ✅ Sync branches access
            $this->syncUserAllowedBranches($user);            
            // Only sync roles if not locked
          if (! $this->is_locked_role) {
                if ($this->access_type === 'hr_app_only') {
                    $user->syncRoles([]); // تطبيق فقط = بدون أدوار للنظام
                } else {
                    $user->syncRoles([$this->role]);
                }
            }

            
            $this->dispatch('toast', type: 'success', message: tr('User updated successfully'));
        } else {
            // Create New
            $password = Str::random(10);
            
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($password),
                'saas_company_id' => $companyId,
                'employee_id' => $this->selectedEmployeeId,
                'access_scope' => $this->access_scope,
                'access_type'  => $this->access_type,
                'is_active' => $this->is_active,
            ]);
            $this->syncUserAllowedBranches($user);
            if ($this->access_type === 'system_and_app') {
                $user->assignRole($this->role);
            }


            // ارسال ايميل تعيين كلمة المرور فوراً
            if (method_exists($user, 'sendWithAuthKitPasswordReset')) {
                 $user->sendWithAuthKitPasswordReset();
            }

            $this->dispatch('toast', type: 'success', message: tr('User created successfully and email sent'));
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->name = '';
        $this->email = '';
        $this->role = '';
        $this->access_scope = 'my_branch';
        $this->access_type  = 'system_and_app';
        $this->selectedEmployeeId = null;
        $this->employeeSearch = '';
        $this->editingId = null;
        $this->is_locked_role = false;
        $this->needs_employee_link = false;
        $this->allowed_branch_ids = [];
    }

    public function sendPasswordReset($id)
    {
        $user = User::where('saas_company_id', $this->getCompanyId())->findOrFail($id);
        
        // التحقق من وجود الدالة وإرسال الإيميل
        if (method_exists($user, 'sendWithAuthKitPasswordReset')) {
             try {
                 $user->sendWithAuthKitPasswordReset();
                 $this->dispatch('toast', type: 'success', message: tr('Password reset link sent successfully to') . ' ' . $user->email);
             } catch (\Exception $e) {
                 $this->dispatch('toast', type: 'error', message: tr('Failed to send email. Please check mail settings.'));
             }
        } else {
             $this->dispatch('toast', type: 'error', message: tr('Password reset functionality is not configured correctly.'));
        }
    }

    public function toggleStatus($id)
    {
        $user = User::where('saas_company_id', $this->getCompanyId())->findOrFail($id);
        
        $user->is_active = ! $user->is_active;
        $user->save();

        $status = $user->is_active ? tr('activated') : tr('deactivated');
        $message = str_replace(':status', $status, tr('User :status successfully'));
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function getCompanyId()
    {
        return Auth::user()->saas_company_id;
    }

    public function render()
    {
       $users = User::where('saas_company_id', $this->getCompanyId())
            ->with(['roles', 'employee'])
            ->when($this->search, function($q) {
                $q->where(function ($qq) {
                    $qq->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            });

        $branchId = $this->effectiveBranchId();
        if ($branchId && $this->employeeBranchCol) {
            $users->whereHas('employee', function ($q) use ($branchId) {
                $q->where($this->employeeBranchCol, $branchId);
            });
        }

        $users = $users->paginate(10);

        //  Temporary fix: Fetch all roles but exclude 'saas-admin'
        // We might need to handle tenant-specific roles via team_id or adding the column later.
        $roles = Role::where('name', '!=', 'saas-admin')->get();

        return view('systemsettings::livewire.user-access-control.users', [
            'users' => $users,
            'roles' => $roles
        ]);
    }
    public function updatedAccessType($value)
    {
        if ($value === 'hr_app_only') {
            $this->role = '';
        }
    }
    private function syncUserAllowedBranches(User $user): void
    {
        if (!method_exists($user, 'allowedBranches')) {
            return;
        }

        $companyId = $this->getCompanyId();
        $scope = $this->access_scope ?? 'all_branches';

        // all_branches => نخلي pivot فاضي (لأنه غير محتاج)
        if ($scope === 'all_branches') {
            $user->allowedBranches()->sync([]);
            return;
        }

        $ids = [];

        // my_branch => ناخذ فرع الموظف المرتبط
        if ($scope === 'my_branch') {
            $bid = (int) ($user->employee?->branch_id ?? 0);

            // لو لسه ما ارتبط employee داخل نفس الحفظ
            if ($bid <= 0 && $this->selectedEmployeeId) {
                $bid = (int) DB::table('employees')->where('id', $this->selectedEmployeeId)->value('branch_id');
            }

            if ($bid > 0) $ids = [$bid];
        }

        // selected_branches => من الفورم
        if ($scope === 'selected_branches') {
            $ids = array_values(array_unique(array_map('intval', $this->allowed_branch_ids)));
        }

        // تأكد أنها فروع لنفس الشركة
        if (!empty($ids)) {
            $ids = DB::table('branches')
                ->where('saas_company_id', $companyId)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn($v) => (int)$v)
                ->all();
        }

        $syncData = [];
        foreach ($ids as $id) {
            $syncData[(int) $id] = ['saas_company_id' => $companyId];
        }

        $user->allowedBranches()->sync($syncData);
    }
}





