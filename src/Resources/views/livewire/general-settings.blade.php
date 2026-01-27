@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('General Settings')"
        :subtitle="tr('Manage general system settings and configurations')"
        class="!flex-col !items-start !justify-start !gap-1"
        titleSize="xl"
    />
@endsection

<div class="space-y-6">
    {{-- Settings Buttons Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {{-- Organizational Structure Settings --}}
        @can('settings.organizational.view')
        <x-ui.settings-button
            href="{{ route('company-admin.settings.organizational-structure') }}"
            icon="fa-sitemap"
            :title="tr('Organizational Structure Settings')"
            :description="tr('Configure departments, positions, and organizational hierarchy')"
        />
        @endcan

        {{-- Approval Sequence Settings --}}
        @can('settings.approval.manage')
        <x-ui.settings-button
            href="#"
            icon="fa-tasks"
            :title="tr('Approval Sequence Settings')"
            :description="tr('Set up approval workflows and sequences')"
        />
        @endcan

        {{-- Lists Settings --}}
        @can('settings.lists.manage')
        <x-ui.settings-button
            href="#"
            icon="fa-list"
            :title="tr('Lists Settings')"
            :description="tr('Manage system lists and dropdown options')"
        />
        @endcan

        {{-- User Access Control --}}
        @canany(['uac.users.view', 'uac.roles.view'])
        <x-ui.settings-button
            href="{{ route('company-admin.settings.user-access-control') }}"
            icon="fa-user-shield"
            :title="tr('User Access Control')"
            :description="tr('Control user permissions and access levels')"
        />
        @endcanany

        {{-- Currencies --}}
        @can('settings.currencies.manage')
        <x-ui.settings-button
            href="#"
            icon="fa-coins"
            :title="tr('Currencies')"
            :description="tr('Manage currencies and exchange rates')"
        />
        @endcan

        {{-- Calendar --}}
        @can('settings.calendar.manage')
        <x-ui.settings-button
            href="#"
            icon="fa-calendar-alt"
            :title="tr('Calendar')"
            :description="tr('Configure calendar settings and working days')"
        />
        @endcan

        {{-- User Activity Log --}}
        @can('logs.view')
        <x-ui.settings-button
            href="#"
            icon="fa-history"
            :title="tr('User Activity Log')"
            :description="tr('View and manage user activity logs')"
        />
        @endcan

        {{-- Institutional Identity --}}
        @can('settings.branding.view')
        <x-ui.settings-button
            href="#"
            icon="fa-id-card"
            :title="tr('Institutional Identity')"
            :description="tr('Configure company logo, colors, and branding')"
        />
        @endcan

        {{-- Backup --}}
        @can('settings.backup.view')
        <x-ui.settings-button
            href="#"
            icon="fa-database"
            :title="tr('Backup')"
            :description="tr('Manage system backups and restore points')"
        />
        @endcan
    </div>
</div>





