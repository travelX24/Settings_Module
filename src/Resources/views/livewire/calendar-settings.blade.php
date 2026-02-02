@php
    $locale = app()->getLocale();
    $isRtl  = in_array(substr($locale, 0, 2), ['ar','fa','ur','he']);

    $savedLabel = $saved_calendar_type === 'hijri' ? tr('Hijri calendar') : tr('Gregorian calendar');
    $currentLabel = $calendar_type === 'hijri' ? tr('Hijri calendar') : tr('Gregorian calendar');

    $isDirty = ($calendar_type !== $saved_calendar_type);

    $headerAlign = $isRtl ? '!items-end !text-right' : '!items-start !text-left';
@endphp

@section('topbar-left-content')
    <x-ui.page-header
        :title="tr('Calendar Settings')"
        :subtitle="tr('Choose the default calendar type for the company')"
        class="!flex-col {{ $headerAlign }} !justify-start !gap-1"
        titleSize="xl"
    />
@endsection

<div class="space-y-6">
    {{-- Toast component (required) --}}
    <x-ui.toast />

    {{-- Convert session flashes to toast (fallback for redirects/navigation) --}}
    @if (session('success'))
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'success', message: @js(session('success')) }
                }));
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'error', message: @js(session('error')) }
                }));
            });
        </script>
    @endif

    {{-- Info: system-wide impact --}}
    <div class="rounded-2xl border border-[color:var(--brand-from)]/20 bg-white p-5 shadow-sm">
        <div class="flex items-start gap-3 {{ $isRtl ? 'flex-row-reverse text-right' : 'text-left' }}">
            <div class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl
                        bg-gradient-to-r from-[color:var(--brand-from)] via-[color:var(--brand-via)] to-[color:var(--brand-to)]
                        text-white shadow">
                {{-- info icon --}}
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zM9 9a1 1 0 000 2h1v3a1 1 0 102 0v-4a1 1 0 00-1-1H9zm1-4a1.25 1.25 0 100 2.5A1.25 1.25 0 0010 5z" clip-rule="evenodd"/>
                </svg>
            </div>

            <div class="flex-1">
                <div class="font-semibold text-gray-900">
                    {{ tr('Important') }}
                </div>
                <div class="mt-1 text-sm text-gray-600 leading-relaxed">
                    {{ tr('This setting is applied across the whole system for this company.') }}
                    <span class="block mt-1">
                        {{ tr('It affects how dates are displayed and handled in forms, reports, and all related modules.') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <x-ui.card class="p-6">
        <div class="space-y-5">
            {{-- Saved vs Current --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="space-y-1">
                    <div class="text-sm font-medium text-gray-900">
                        {{ tr('Effective (saved) calendar type') }}
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-sm
                                     {{ $saved_calendar_type === 'hijri'
                                        ? 'border-amber-200 bg-amber-50 text-amber-800'
                                        : 'border-blue-200 bg-blue-50 text-blue-800' }}">
                            {{ $savedLabel }}
                        </span>

                        @if (!empty($saved_updated_human))
                            <span class="text-xs text-gray-500">
                                {{ tr('Last updated') }}: {{ $saved_updated_human }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="space-y-1 {{ $isRtl ? 'text-right' : 'text-left' }}">
                    <div class="text-sm font-medium text-gray-900">
                        {{ tr('Currently selected') }}
                    </div>
                    <div class="flex flex-wrap items-center gap-2 {{ $isRtl ? 'justify-end' : 'justify-start' }}">
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-sm
                                     {{ $calendar_type === 'hijri'
                                        ? 'border-amber-200 bg-amber-50 text-amber-800'
                                        : 'border-blue-200 bg-blue-50 text-blue-800' }}">
                            {{ $currentLabel }}
                        </span>

                        @if ($isDirty)
                            <span class="inline-flex items-center rounded-full border border-yellow-200 bg-yellow-50 px-3 py-1 text-sm text-yellow-800">
                                {{ tr('Not saved yet') }}
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-3 py-1 text-sm text-green-800">
                                {{ tr('Saved') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <hr class="border-gray-100">

            {{-- Select --}}
            <div class="space-y-2">
                <div class="text-sm font-medium text-gray-900">
                    {{ tr('Default calendar type') }}
                </div>

                <div>
                    <x-ui.select wire:model.live="calendar_type" class="w-full">
                        <option value="hijri">{{ tr('Hijri calendar') }}</option>
                        <option value="gregorian">{{ tr('Gregorian calendar') }}</option>
                    </x-ui.select>

                    @error('calendar_type')
                        <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                    @enderror

                    <div class="mt-2 text-xs text-gray-500">
                        {{ tr('Tip: Choose the calendar that most of your company uses. You can change it later if needed.') }}
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2">
                @if ($isDirty)
                    <x-ui.secondary-button type="button" wire:click="resetToSaved" wire:loading.attr="disabled">
                        {{ tr('Revert changes') }}
                    </x-ui.secondary-button>
                @endif

                <x-ui.primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ tr('Save') }}</span>
                    <span wire:loading wire:target="save">{{ tr('Saving...') }}</span>
                </x-ui.primary-button>
            </div>
        </div>
    </x-ui.card>
</div>
