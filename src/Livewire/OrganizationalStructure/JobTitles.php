<?php

namespace Athka\SystemSettings\Livewire\OrganizationalStructure;

use Athka\SystemSettings\Models\JobTitle;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class JobTitles extends Component
{
    public $search = '';
    public $showModal = false;
    public $editingId = null;

    /** ✅ NEW: view mode (table | cards) */
    public string $viewMode = 'table';

    /** ✅ OPTIONAL: keep viewMode in query string */
    protected $queryString = [
        'viewMode' => ['except' => 'table'],
    ];

    // Form fields
    public $name = '';
    public $code = '';
    public $description = '';
    public $is_active = true;

    // Stats
    public $stats = [];

    public function mount()
    {
        $this->authorize('settings.organizational.view');

        $mode = request()->query('viewMode', 'table');
        $this->viewMode = in_array($mode, ['table', 'cards'], true) ? $mode : 'table';

        $this->loadStats();
    }

    public function updatedSearch()
    {
        // No server pagination (x-ui.table handles pagination on the client)
    }

    public function updatedViewMode()
    {
        // No server pagination (x-ui.table handles pagination on the client)
    }

    /**
     * Translate + replace placeholders.
     * Example: $this->trp('Job title: :name', ['name' => 'Manager'])
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

        $total = JobTitle::forCompany($companyId)->count();
        $active = JobTitle::forCompany($companyId)->active()->count();
        $inactive = $total - $active;

        // Employee Distribution (Top 3 Job Titles)
        $distribution = JobTitle::forCompany($companyId)
            ->withCount('employees')
            ->orderBy('employees_count', 'desc')
            ->limit(3)
            ->get()
            ->map(fn($jt) => [
                'name' => $jt->name,
                'count' => $jt->employees_count
            ])->toArray();

        $this->stats = [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'distribution' => $distribution,
        ];
    }

    public function openAddModal()
    {
        $this->authorize('settings.organizational.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $this->authorize('settings.organizational.manage');
        $jobTitle = JobTitle::forCompany($this->getCompanyId())->findOrFail($id);

        $this->editingId = $jobTitle->id;
        $this->name = $jobTitle->name;
        $this->code = $jobTitle->code ?? '';
        $this->description = $jobTitle->description ?? '';
        $this->is_active = $jobTitle->is_active;
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
        $this->is_active = true;
    }

    public function save()
    {
        $this->authorize('settings.organizational.manage');
        $companyId = $this->getCompanyId();

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('job_titles')
                    ->where('saas_company_id', $companyId)
                    ->ignore($this->editingId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                \Illuminate\Validation\Rule::unique('job_titles')
                    ->where('saas_company_id', $companyId)
                    ->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'description' => $this->description ?: null,
            'is_active' => (bool) $this->is_active,
            'saas_company_id' => $companyId,
        ];

        if ($this->editingId) {
            $jobTitle = JobTitle::forCompany($companyId)->findOrFail($this->editingId);
            $jobTitle->update($data);

            session()->flash('status', tr('Job title updated successfully'));

            $this->dispatch('toast',
                type: 'success',
                title: tr('Job title updated successfully'),
                message: (string) $jobTitle->name,
            );
        } else {
            $jobTitle = JobTitle::create($data);

            session()->flash('status', tr('Job title created successfully'));

            $this->dispatch('toast',
                type: 'success',
                title: tr('Job title created successfully'),
                message: (string) $jobTitle->name,
            );
        }

        $this->closeModal();
        $this->loadStats();
    }

    public function delete($id)
    {
        $this->authorize('settings.organizational.manage');
        $jobTitle = JobTitle::forCompany($this->getCompanyId())->findOrFail($id);

        if (! $jobTitle->canDelete()) {
            $employeesCount = $jobTitle->employees()->count();

            $message = $this->trp(
                'Cannot delete ":name" because it is linked to :count employees.',
                ['name' => $jobTitle->name, 'count' => $employeesCount]
            );
            $message .= ' ' . tr('Please transfer employees first or deactivate instead of deleting.');

            $this->dispatch('toast',
                type: 'error',
                title: tr('Deletion Blocked'),
                message: $message,
            );

            return;
        }

        $name = $jobTitle->name;
        $jobTitle->delete();

        session()->flash('status', tr('Job title deleted successfully'));

        $this->dispatch('toast',
            type: 'success',
            title: tr('Job title deleted successfully'),
            message: (string) $name,
        );

        $this->loadStats();
    }

    public function toggleActive($id)
    {
        $this->authorize('settings.organizational.manage');
        $jobTitle = JobTitle::forCompany($this->getCompanyId())->findOrFail($id);

        $jobTitle->update(['is_active' => ! $jobTitle->is_active]);

        $title = $jobTitle->is_active
            ? tr('Job title activated successfully')
            : tr('Job title deactivated successfully');

        session()->flash('status', $title);

        $this->dispatch('toast',
            type: 'success',
            title: $title,
            message: (string) $jobTitle->name,
        );

        $this->loadStats();
    }

    public function export()
    {
        $companyId = $this->getCompanyId();

        $jobTitles = JobTitle::forCompany($companyId)
            ->withCount('employees')
            ->get();

        $filename = 'job_titles_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($jobTitles) {
            $file = fopen('php://output', 'w');

            // BOM for UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Headers
            fputcsv($file, [
                tr('Job Title Name'),
                tr('Code'),
                tr('Employees Count'),
                tr('Status'),
            ]);

            // Data
            foreach ($jobTitles as $jobTitle) {
                fputcsv($file, [
                    $jobTitle->name,
                    $jobTitle->code ?? '',
                    (int) ($jobTitle->employees_count ?? 0),
                    $jobTitle->is_active ? tr('Active') : tr('Inactive'),
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

        return Auth::user()->saas_company_id;
    }

    public function render()
    {
        $companyId = $this->getCompanyId();

        $query = JobTitle::forCompany($companyId)
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%')
                        ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('name');

        // ✅ No server pagination (x-ui.table handles pagination on the client)
        $jobTitles = $query->withCount('employees')->get();

        return view('systemsettings::livewire.organizational-structure.job-titles', [
            'jobTitles' => $jobTitles,
        ]);
    }
}





