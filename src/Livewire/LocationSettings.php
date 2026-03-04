<?php
/**
 * File: c:\xampp\htdocs\Laravel\Athka_HR\HrWithModules\Athka\SystemSettings\Livewire\LocationSettings.php
 */

namespace Athka\SystemSettings\Livewire;

use Livewire\Component;

class LocationSettings extends Component
{
    public function mount()
    {
        $this->authorize('locations.view');
    }

    public function render()
    {
        return view('systemsettings::livewire.location-settings')
            ->layout('layouts.company-admin');
    }
}





