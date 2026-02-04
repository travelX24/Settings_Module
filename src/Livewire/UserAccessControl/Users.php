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

    protected $listeners = [
        'refreshUsers' => '$refresh',
        'open-add-user-modal' => 'openAddModal',
    ];

    public function mount()
    {
        $this->loadPermissionGroups();
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
        $employee = Employee::find($employeeId);
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
        $this->display_branch = tr('Main Branch'); // Placeholder until "Branch" concept is clearer
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
        $this->is_active = $user->is_active ?? true;
        
        // Check if user needs employee link (e.g. company admin created initially)
        $this->needs_employee_link = is_null($user->employee_id);

        // Load Role
        $roleName = $user->roles->first()?->name ?? '';
        $this->role = $roleName;

        // Lock role ONLY if it's the primary account (the first user created for the company)
        $firstUser = User::where('saas_company_id', $this->getCompanyId())->orderBy('id', 'asc')->first();
        $this->is_locked_role = ($id === $firstUser->id);

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
            'role' => $this->is_locked_role ? 'nullable' : ['required', 'exists:roles,name'],
            'access_scope' => ['required', 'in:my_branch,all_branches'],
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
            
            // Only sync roles if not locked
            if (!$this->is_locked_role) {
                $user->syncRoles([$this->role]);
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
                'is_active' => $this->is_active,
            ]);

            $user->assignRole($this->role);

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
        $this->selectedEmployeeId = null;
        $this->employeeSearch = '';
        $this->editingId = null;
        $this->is_locked_role = false;
        $this->needs_employee_link = false;
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
                $q->where('name', 'like', '%'.$this->search.'%')
                  ->orWhere('email', 'like', '%'.$this->search.'%');
            })
            ->paginate(10);

        // ✅ Temporary fix: Fetch all roles but exclude 'saas-admin'
        // We might need to handle tenant-specific roles via team_id or adding the column later.
        $roles = Role::where('name', '!=', 'saas-admin')->get();

        return view('systemsettings::livewire.user-access-control.users', [
            'users' => $users,
            'roles' => $roles
        ]);
    }
}





