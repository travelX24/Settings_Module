<?php
/**
 * File: c:\xampp\htdocs\Laravel\Athka_HR\HrWithModules\Athka\SystemSettings\Livewire\GeneralSettings.php
 */

namespace Athka\SystemSettings\Livewire;

use Livewire\Component;

class GeneralSettings extends Component
{
    private const VIEW_PERMISSIONS = [
        'settings.general.view',
        'settings.lists.view',
        'settings.organizational.view',
        'settings.organizational.manage',
        'uac.users.view',
        'uac.users.manage',
        'uac.roles.view',
        'uac.roles.manage',
        'settings.approval.view',
        'settings.approval.manage',
        'settings.calendar.manage',
        'settings.currencies.manage',
        'settings.branding.view',
        'settings.branding.manage',
        'settings.backup.view',
        'settings.backup.manage',
        'logs.view',
        'logs.export',
    ];

    public function mount()
    {
        abort_unless($this->userCanAny(self::VIEW_PERMISSIONS), 403);
    }

    private function userCanAny(array $permissions): bool
    {
        $user = auth()->user();

        return $user && collect($permissions)->contains(fn ($permission) => $user->can($permission));
    }

    public function render()
    {
        return view('systemsettings::livewire.general-settings')
            ->layout('layouts.company-admin');
    }
}