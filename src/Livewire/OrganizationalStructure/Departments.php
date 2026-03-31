<?php

namespace Athka\SystemSettings\Livewire\OrganizationalStructure;

use Livewire\Component;
use Livewire\WithPagination;
use App\Services\ExcelExportService;
use Athka\SystemSettings\Models\Department;
use Athka\SystemSettings\Services\OrganizationService;
use Athka\SystemSettings\Livewire\OrganizationalStructure\Traits\HandleDepartmentLogic;

class Departments extends Component
{
    use WithPagination, HandleDepartmentLogic;

    public $search = '';
    public $rootDepartmentId = 'all';
    public $viewMode = 'table';
    public $showModal = false;
    public $editingId = null;

    // Form fields
    public $name = '', $code = '', $description = '', $manager_id = null, $parent_id = null, $is_active = true;
    public $stats = [];
    public $hasChildren = false;

    protected $orgService;

    public function boot(OrganizationService $service)
    {
        $this->orgService = $service;
    }

    public function mount()
    {
        $this->authorize('settings.organizational.view');
        $this->loadStats();
    }

    public function render()
    {
        $companyId = $this->getCompanyId();
        
        $query = Department::forCompany($companyId)
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->rootDepartmentId !== 'all', function($q) use ($companyId) {
                $ids = array_merge([(int)$this->rootDepartmentId], $this->orgService->getAllDescendantIds((int)$this->rootDepartmentId, $companyId));
                $q->whereIn('id', $ids);
            })
            ->with(['manager', 'parent'])
            ->withCount('employees')
            ->orderBy('name');

        $departments = $query->paginate(15);
        
        $departments->getCollection()->transform(function($dept) use ($companyId) {
            $dept->employees_count_display = $this->orgService->getCumulativeEmployeeCount($dept->id, $companyId);
            return $dept;
        });

        return view('systemsettings::livewire.organizational-structure.departments', [
            'departments' => $departments,
            'managers' => $this->orgService->getManagersList($companyId, app()->getLocale()),
            'parentDepartments' => Department::forCompany($companyId)
                ->when($this->editingId, fn($q) => $q->where('id', '!=', $this->editingId))
                ->whereNull('parent_id') // Only root departments can be parents
                ->get(),
            'rootDepartments' => Department::forCompany($companyId)
                ->root()
                ->get()
                ->map(fn($d) => ['value' => $d->id, 'label' => $d->name])
                ->toArray(),
        ]);
    }

    public function clearAllFilters()
    {
        $this->search = '';
        $this->rootDepartmentId = 'all';
        $this->resetPage();
    }

    public function openAddModal()
    {
        $this->authorize('settings.organizational.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $dept = Department::withCount('children')->findOrFail($id);
        $this->editingId = $id;
        $this->name = $dept->name;
        $this->code = $dept->code;
        $this->description = $dept->description;
        $this->manager_id = $dept->manager_id;
        $this->parent_id = $dept->parent_id;
        $this->is_active = $dept->is_active;
        $this->hasChildren = $dept->children_count > 0;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
        $this->editingId = null;
    }

    public function export(ExcelExportService $exporter)
    {
        $companyId = $this->getCompanyId();
        $departments = Department::forCompany($companyId)->with(['manager', 'parent'])->get();
        
        $filename = tr('Departments') . '_' . date('Y-m-d');
        $locale = app()->getLocale();

        $headers = [tr('Name'), tr('Code'), tr('Manager'), tr('Parent'), tr('Status')];
        
        $data = $departments->map(function ($d) use ($locale) {
            $managerName = '-';
            if ($d->manager) {
                $managerName = ($locale === 'ar') 
                    ? ($d->manager->name_ar ?? $d->manager->name_en) 
                    : ($d->manager->name_en ?? $d->manager->name_ar);
            }

            return [
                $d->name, 
                $d->code ?? '-', 
                $managerName, 
                $d->parent?->name ?? '-', 
                $d->is_active ? tr('Active') : tr('Inactive')
            ];
        })->toArray();

        return $exporter->export($filename, $headers, $data);
    }

    public function resetForm()
    {
        $this->reset(['name', 'code', 'description', 'manager_id', 'parent_id', 'is_active', 'editingId', 'hasChildren']);
    }
}
