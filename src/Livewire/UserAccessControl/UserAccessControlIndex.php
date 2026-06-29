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

    public function mount()
    {
        if (! auth()->user()->can('uac.users.view') && ! auth()->user()->can('uac.roles.view')) {
            abort(403);
        }

        if ($this->activeTab === 'users' && ! auth()->user()->can('uac.users.view')) {
            $this->activeTab = 'roles';
        }

        if ($this->activeTab === 'roles' && ! auth()->user()->can('uac.roles.view')) {
            $this->activeTab = 'users';
        }
    }

    public function setActiveTab($tab)
    {
        if ($tab === 'users' && ! auth()->user()->can('uac.users.view')) {
            abort(403);
        }

        if ($tab === 'roles' && ! auth()->user()->can('uac.roles.view')) {
            abort(403);
        }

        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('systemsettings::livewire.user-access-control.index')
            ->layout('layouts.company-admin');
    }
}