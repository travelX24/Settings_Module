<div class="space-y-4">
    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2 px-1">
        <span class="w-1 h-5 bg-[color:var(--brand-via)] rounded-full"></span>
        {{ tr('Attendance Tracking Policy') }}
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach(['check_in_only' => ['icon' => 'fa-sign-in-alt', 'title' => tr('Attendance Only'), 'desc' => tr('Track check-in only.')], 'check_in_out' => ['icon' => 'fa-exchange-alt', 'title' => tr('Attendance & Departure'), 'desc' => tr('Track both check-in and check-out.')], 'manual' => ['icon' => 'fa-hand-paper', 'title' => tr('Manual Entry'), 'desc' => tr('Disable automated logging.')]] as $key => $opt)
        <div 
            @can('settings.attendance.manage')
            wire:click="setTrackingPolicy('{{ $key }}')" 
            class="p-4 border rounded-xl cursor-pointer transition-all {{ $trackingPolicy === $key ? 'border-[color:var(--brand-via)] bg-[color:var(--brand-via)]/5 ring-1 ring-[color:var(--brand-via)]/20 shadow-sm' : 'border-gray-200 bg-white hover:border-gray-300' }}"
            @else
            class="p-4 border rounded-xl cursor-not-allowed opacity-80 {{ $trackingPolicy === $key ? 'border-[color:var(--brand-via)] bg-[color:var(--brand-via)]/5' : 'border-gray-200 bg-gray-50' }}"
            @endcan
        >
            <div class="flex items-center justify-between mb-2">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ $trackingPolicy === $key ? 'bg-[color:var(--brand-via)] text-white' : 'bg-gray-100 text-gray-400' }}">
                    <i class="fas {{ $opt['icon'] }}"></i>
                </div>
                @if($trackingPolicy === $key)
                    <i class="fas fa-check-circle text-[color:var(--brand-via)] text-lg"></i>
                @endif
            </div>
            <span class="font-bold text-gray-800">{{ $opt['title'] }}</span>
            <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ $opt['desc'] }}</p>
        </div>
        @endforeach
    </div>
</div>





