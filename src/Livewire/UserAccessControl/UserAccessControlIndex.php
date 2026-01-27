<?php
/**
 * File: c:\xampp\htdocs\Laravel\Athka_HR\HrWithModules\Athka\SystemSettings\Livewire\UserAccessControl\UserAccessControlIndex.php
 */

namespace Athka\SystemSettings\Livewire\UserAccessControl;

use Livewire\Component;

class UserAccessControlIndex extends Component
{
    public $activeTab = 'users';

    protected $queryString = [
        'activeTab' => ['except' => 'users'],
    ];

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('systemsettings::livewire.user-access-control.index')
            ->layout('layouts.company-admin');
    }
}





