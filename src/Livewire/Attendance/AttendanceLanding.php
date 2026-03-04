<?php
/**
 * File: c:\xampp\htdocs\Laravel\Athka_HR\HrWithModules\Athka\SystemSettings\Livewire\Attendance\AttendanceLanding.php
 */

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;

class AttendanceLanding extends Component
{
    public function mount()
    {
        $this->authorize('settings.attendance.view');
    }

    public function render()
    {
        return view('systemsettings::livewire.attendance-landing')
            ->layout('layouts.company-admin');
    }
}





