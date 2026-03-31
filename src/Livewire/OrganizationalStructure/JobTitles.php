<?php

namespace Athka\SystemSettings\Livewire\OrganizationalStructure;

use Livewire\Component;
use App\Services\ExcelExportService;
use Athka\SystemSettings\Models\JobTitle;
use Athka\SystemSettings\Services\OrganizationService;
use Athka\SystemSettings\Livewire\OrganizationalStructure\Traits\HandleJobTitleLogic;

class JobTitles extends Component
{
    use HandleJobTitleLogic;

    public $search = '';
    public $showModal = false;
    public $editingId = null;
    public $viewMode = 'table';

    // Form fields
    public $name = '', $code = '', $description = '', $is_active = true;
    public $stats = [];

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
        $query = JobTitle::forCompany($this->getCompanyId())
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%"))
            ->withCount('employees')
            ->orderBy('name');

        return view('systemsettings::livewire.organizational-structure.job-titles', [
            'jobTitles' => $query->get(),
        ]);
    }

    public function clearAllFilters()
    {
        $this->search = '';
    }

    public function openAddModal()
    {
        $this->authorize('settings.organizational.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $jt = JobTitle::findOrFail($id);
        $this->editingId = $id;
        $this->name = $jt->name;
        $this->code = $jt->code;
        $this->description = $jt->description;
        $this->is_active = $jt->is_active;
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
        $jobTitles = JobTitle::forCompany($companyId)->get();
        
        $filename = tr('Job Titles') . '_' . date('Y-m-d');
        $headers = [tr('Name'), tr('Code'), tr('Status')];
        
        $data = $jobTitles->map(function ($jt) {
            return [
                $jt->name, 
                $jt->code ?? '-', 
                $jt->is_active ? tr('Active') : tr('Inactive')
            ];
        })->toArray();

        return $exporter->export($filename, $headers, $data);
    }

    public function resetForm()
    {
        $this->reset(['name', 'code', 'description', 'is_active', 'editingId']);
    }
}
