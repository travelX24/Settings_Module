<?php

namespace Athka\SystemSettings\Livewire\OrganizationalStructure\Traits;

use Athka\SystemSettings\Models\JobTitle;

trait HandleJobTitleLogic
{
    public function loadStats()
    {
        $this->stats = $this->orgService->getJobTitleStats($this->getCompanyId());
    }

    public function save()
    {
        $this->authorize('settings.organizational.manage');
        $companyId = $this->getCompanyId();

        $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('job_titles')->where(function ($query) use ($companyId) {
                    return $query->where('saas_company_id', $companyId);
                })->ignore($this->editingId)
            ],
            'code' => 'nullable|string|max:50'
        ]);

        if (!$this->is_active && $this->editingId) {
            $jt = JobTitle::find($this->editingId);
            if ($jt && $jt->is_active && $jt->employees()->exists()) {
                $this->addError('is_active', tr('Cannot deactivate because there are linked employees.'));
                return;
            }
        }

        $this->orgService->saveJobTitle($companyId, [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ], $this->editingId);

        $this->showModal = false;
        $this->resetForm();
        $this->loadStats();
        $this->dispatch('toast', type: 'success', message: tr('Job title saved successfully'));
    }

    public function delete($id)
    {
        $this->authorize('settings.organizational.manage');
        $jt = JobTitle::findOrFail($id);
        
        if (!$jt->canDelete()) {
            $this->dispatch('toast', type: 'error', title: tr('Deletion Blocked'), message: tr('Job title is linked to employees.'));
            return;
        }

        $jt->delete();
        $this->loadStats();
        $this->dispatch('toast', type: 'success', message: tr('Deleted successfully'));
    }

    public function toggleActive($id)
    {
        $this->authorize('settings.organizational.manage');
        $jt = JobTitle::findOrFail($id);
        
        if ($jt->is_active && $jt->employees()->exists()) {
            $this->dispatch('toast', type: 'error', title: tr('Deactivation Blocked'), message: tr('Cannot deactivate because there are linked employees.'));
            return;
        }

        $jt->update(['is_active' => !$jt->is_active]);
        $this->loadStats();
        $this->dispatch('toast', type: 'success', message: tr('Status updated successfully'));
    }

    protected function getCompanyId(): int
    {
        return auth()->user()->saas_company_id ?? 0;
    }
}
