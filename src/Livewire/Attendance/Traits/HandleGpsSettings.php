<?php

namespace Athka\SystemSettings\Livewire\Attendance\Traits;

use Athka\SystemSettings\Models\AttendanceGpsLocation;

trait HandleGpsSettings
{
    public $gpsData = [
        'name' => '',
        'latitude' => '',
        'longitude' => '',
        'radius' => 100,
        'is_active' => true,
    ];

    public function openGpsModal($id = null)
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
        
        if ($id) {
            $loc = AttendanceGpsLocation::find($id);
            $this->selectedId = $id;
            $this->gpsData = [
                'name' => $loc->name,
                'latitude' => $loc->latitude,
                'longitude' => $loc->longitude,
                'radius' => $loc->radius,
                'is_active' => (bool)$loc->is_active,
            ];
        } else {
            $this->selectedId = null;
            $this->gpsData = ['name' => '', 'latitude' => '', 'longitude' => '', 'radius' => 100, 'is_active' => true];
        }
        $this->showGpsModal = true;
    }

    public function saveGpsLocation()
    {
        $this->authorize('settings.attendance.manage');
        $this->validate([
            'gpsData.name' => 'required|min:3',
            'gpsData.latitude' => 'required|numeric',
            'gpsData.longitude' => 'required|numeric',
            'gpsData.radius' => 'required|integer|min:10',
        ]);

        $this->attendanceSettingService->saveGpsLocation(
            auth()->user()->saas_company_id,
            $this->gpsData,
            $this->selectedId
        );

        $this->showGpsModal = false;
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('GPS location saved successfully.'));
    }

    public function deleteGpsLocation($id)
    {
        $this->authorize('settings.attendance.manage');
        AttendanceGpsLocation::destroy($id);
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('GPS location deleted.'));
    }
}
