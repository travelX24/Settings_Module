<?php

namespace Athka\SystemSettings;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Athka\Saas\Http\Middleware\EnsureCompanyAdmin;
use Athka\Saas\Http\Middleware\ForceCompanyDomain;

class SystemSettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadViews();
        $this->loadMigrations();
        $this->loadRoutes();
        $this->registerLivewireComponents();
    }

    /**
     * Load module views
     */
    protected function loadViews(): void
    {
        $this->loadViewsFrom(
            __DIR__ . '/Resources/views',
            'systemsettings'
        );
    }

    /**
     * Load module migrations
     */
    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }

    /**
     * Load module routes
     */
    protected function loadRoutes(): void
    {
      Route::middleware([
                'web',
                'auth',
                EnsureCompanyAdmin::class,
                ForceCompanyDomain::class,
                'company.domain',
                \Athka\Saas\Http\Middleware\SetCompanyCalendarType::class,
                \Athka\Saas\Http\Middleware\SetCompanyTimezone::class,
            ])

            ->prefix('company-admin/settings')
            ->name('company-admin.settings.')
            ->group(function () {
                require __DIR__ . '/Routes/web.php';
            });
    }

    /**
     * Register Livewire components
     */
    protected function registerLivewireComponents(): void
    {
        if (class_exists(Livewire::class)) {

            Livewire::addPersistentMiddleware([
                \Athka\Saas\Http\Middleware\SetCompanyCalendarType::class,
                \Athka\Saas\Http\Middleware\SetCompanyTimezone::class,
            ]);

            Livewire::component(
                'systemsettings.organizational-structure.departments',
                \Athka\SystemSettings\Livewire\OrganizationalStructure\Departments::class
            );
            
            Livewire::component(
                'systemsettings.organizational-structure.job-titles',
                \Athka\SystemSettings\Livewire\OrganizationalStructure\JobTitles::class
            );

            Livewire::component(
                'systemsettings.user-access-control.users',
                \Athka\SystemSettings\Livewire\UserAccessControl\Users::class
            );

            Livewire::component(
                'systemsettings.user-access-control.roles',
                \Athka\SystemSettings\Livewire\UserAccessControl\Roles::class
            );

            Livewire::component(
                'systemsettings.attendance.attendance-settings',
                \Athka\SystemSettings\Livewire\Attendance\AttendanceSettings::class
            );

            Livewire::component(
                'systemsettings.general-settings',
                \Athka\SystemSettings\Livewire\GeneralSettings::class
            );

            Livewire::component(
                'systemsettings.attendance.landing',
                \Athka\SystemSettings\Livewire\Attendance\AttendanceLanding::class
            );

            Livewire::component(
                'systemsettings.location-settings',
                \Athka\SystemSettings\Livewire\LocationSettings::class
            );

            Livewire::component(
                'systemsettings.attendance.work-schedules',
                \Athka\SystemSettings\Livewire\Attendance\WorkSchedules::class
            );
         

            Livewire::component(
                'systemsettings.attendance.attendance-holidays',
                \Athka\SystemSettings\Livewire\Attendance\AttendanceHolidays::class
            );

            Livewire::component(
                'athka.system-settings.livewire.attendance.attendance-holidays',
                \Athka\SystemSettings\Livewire\Attendance\AttendanceHolidays::class
            );
            Livewire::component(
                'systemsettings.attendance.attendance-leave-settings',
                \Athka\SystemSettings\Livewire\Attendance\AttendanceLeaveSettings::class
            );

            Livewire::component(
                'athka.system-settings.livewire.attendance.attendance-leave-settings',
                \Athka\SystemSettings\Livewire\Attendance\AttendanceLeaveSettings::class
            );

            Livewire::component(
                'systemsettings.organizational-structure.index',
                \Athka\SystemSettings\Livewire\OrganizationalStructure\OrganizationalStructureIndex::class
            );
            Livewire::component(
                'systemsettings.user-access-control.index',
                \Athka\SystemSettings\Livewire\UserAccessControl\UserAccessControlIndex::class
            );

         
            Livewire::component(
                'systemsettings.calendar.calendar-settings',
                \Athka\SystemSettings\Livewire\Calendar\CalendarSettings::class
            );

            Livewire::component(
                'athka.system-settings.livewire.calendar.calendar-settings',
                \Athka\SystemSettings\Livewire\Calendar\CalendarSettings::class
            );


        }
    }
}





