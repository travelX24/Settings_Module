<?php
/**
 * File: c:\xampp\htdocs\Laravel\Athka_HR\HrWithModules\Athka\SystemSettings\Livewire\GeneralSettings.php
 */

namespace Athka\SystemSettings\Livewire;

use Livewire\Component;

class GeneralSettings extends Component
{
    public function mount()
    {
        $this->authorize('settings.general.view');
    }

    public function render()
    {
        return view('systemsettings::livewire.general-settings')
            ->layout('layouts.company-admin');
    }
}





