<?php
/**
 * File: c:\xampp\htdocs\Laravel\Athka_HR\HrWithModules\Athka\SystemSettings\Livewire\OrganizationalStructure\OrganizationalStructureIndex.php
 */

namespace Athka\SystemSettings\Livewire\OrganizationalStructure;

use Livewire\Component;

class OrganizationalStructureIndex extends Component
{
    public $activeTab = 'departments';

    protected $queryString = [
        'activeTab' => ['except' => 'departments'],
    ];

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('systemsettings::livewire.organizational-structure.index')
            ->layout('layouts.company-admin');
    }
}





