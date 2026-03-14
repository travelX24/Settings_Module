<?php

namespace Athka\SystemSettings\Livewire\OrganizationalStructure;

use Livewire\Component;
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

    public function export()
    {
        $companyId = $this->getCompanyId();
        $jobTitles = JobTitle::forCompany($companyId)->get();
        
        $filename = tr('Job Titles') . '_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\""
        ];

        $callback = function () use ($jobTitles) {
            $file = fopen('php://output', 'w');
            
            // إضافة BOM لضمان ظهور اللغة العربية بشكل صحيح في Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [tr('Name'), tr('Code'), tr('Status')]);
            
            foreach ($jobTitles as $jt) {
                fputcsv($file, [
                    $jt->name, 
                    $jt->code, 
                    $jt->is_active ? tr('Active') : tr('Inactive')
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function resetForm()
    {
        $this->reset(['name', 'code', 'description', 'is_active', 'editingId']);
    }
}
