@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Geographic Settings')"
        :subtitle="tr('Configure geographic locations, branches, and location-based settings')"
        class="!flex-col !items-start !justify-start !gap-1"
        titleSize="xl"
    />
@endsection

@section('topbar-actions')
    <x-ui.secondary-button
        href="{{ route('company-admin.settings.general') }}"
        :arrow="false"
        :fullWidth="false"
        class="!px-4 !py-2 !text-sm !rounded-xl !gap-2"
    >
        <i class="fas {{ app()->getLocale() == 'ar' ? 'fa-arrow-right' : 'fa-arrow-left' }} text-xs"></i>
        <span>{{ tr('Back') }}</span>
    </x-ui.secondary-button>
@endsection

<div class="space-y-6">
    {{-- Settings Content --}}
    <div class="bg-white rounded-xl shadow-sm border-2 border-amber-500 p-6">
        <p class="text-gray-600">{{ tr('Geographic settings will be available soon') }}</p>
    </div>
</div>





