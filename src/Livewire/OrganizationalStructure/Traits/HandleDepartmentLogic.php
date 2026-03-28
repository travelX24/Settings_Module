<?php

namespace Athka\SystemSettings\Livewire\OrganizationalStructure\Traits;

use Athka\SystemSettings\Models\Department;

trait HandleDepartmentLogic
{
    public function loadStats()
    {
        $this->stats = $this->orgService->getDepartmentStats($this->getCompanyId());
    }

    public function save()
    {
        $this->authorize('settings.organizational.manage');
        $companyId = $this->getCompanyId();

        $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:10',
            'manager_id' => 'nullable|exists:employees,id',
            'parent_id' => 'nullable|exists:departments,id',
        ]);

        if ($this->parent_id) {
            // Ensure parent is level 1
            $parent = Department::find($this->parent_id);
            if ($parent && $parent->parent_id) {
                $this->addError('parent_id', tr('A sub-department cannot be a parent.'));
                return;
            }
            
            // Ensure this department has no children (if it's to be a sub-department)
            if ($this->editingId && \Athka\SystemSettings\Models\Department::where('parent_id', $this->editingId)->exists()) {
                $this->addError('parent_id', tr('This department has sub-departments and cannot be assigned a parent.'));
                return;
            }
        }

        $this->orgService->saveDepartment($companyId, [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'manager_id' => $this->manager_id,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
        ], $this->editingId);

        $this->showModal = false;
        $this->resetForm();
        $this->resetPage();
        $this->loadStats();
        $this->dispatch('toast', type: 'success', title: tr('Success'), message: tr('Operation completed successfully'));
    }

    public function delete($id)
    {
        $this->authorize('settings.organizational.manage');
        $dept = Department::findOrFail($id);
        
        if (!$dept->canDelete()) {
            $this->dispatch('toast', type: 'error', title: tr('Deletion Blocked'), message: tr('Department has linked employees or sub-departments.'));
            return;
        }

        $dept->delete();
        $this->resetPage();
        $this->loadStats();
        $this->dispatch('toast', type: 'success', message: tr('Deleted successfully'));
    }

    public function toggleActive($id)
    {
        $this->authorize('settings.organizational.manage');
        $dept = Department::findOrFail($id);
        $dept->update(['is_active' => !$dept->is_active]);
        $this->loadStats();
        $this->dispatch('toast', type: 'success', message: tr('Status updated successfully'));
    }

    public function getCompanyId(): int
    {
        return auth()->user()->saas_company_id ?? 0;
    }
}
