<?php
/**
 * File: c:\xampp\htdocs\Laravel\Athka_HR\HrWithModules\Athka\SystemSettings\Livewire\Attendance\AttendanceLanding.php
 */

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;

class AttendanceLanding extends Component
{
    private const VIEW_PERMISSIONS = [
        'settings.attendance.view',
        'settings.attendance.manage',
        'settings.attendance.schedules.view',
        'settings.attendance.schedules.manage',
        'settings.attendance.leaves.view',
        'settings.attendance.leaves.manage',
        'settings.attendance.holidays.view',
        'settings.attendance.holidays.manage',
        'settings.attendance.exceptional.view',
        'settings.attendance.exceptional.manage',
    ];

    public function mount()
    {
        abort_unless(auth()->user() && collect(self::VIEW_PERMISSIONS)->contains(fn ($permission) => auth()->user()->can($permission)), 403);
    }

    public function render()
    {
        return view('systemsettings::livewire.attendance-landing')
            ->layout('layouts.company-admin');
    }
}