<x-ui.modal wire:model="showGroupModal" maxWidth="4xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-lg border border-purple-100 shadow-sm">
                <i class="fas fa-users-cog"></i>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ $isEditingGroup ? tr('Edit Employee Group') : tr('Create New Group') }}</h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Group-Based Attendance Governance') }}</p>
            </div>
        </div>
    </x-slot:title>

    <x-slot:content>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 py-2">
            {{-- Left Column: Configuration --}}
            <div class="lg:col-span-12 lg:grid lg:grid-cols-2 gap-8">
                <div class="space-y-6">
                    {{-- Group Identity Section --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-1 h-3 bg-purple-500 rounded-full"></span>
                            <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Identity & Policy') }}</h4>
                        </div>
                        
                        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm space-y-5">
                            <div class="grid grid-cols-1 gap-5">
                                <x-ui.input 
                                    label="{{ tr('Group Name') }}" 
                                    wire:model.defer="newGroup.name" 
                                    placeholder="{{ tr('e.g. Sales Team') }}" 
                                    required 
                                    class="!py-3 !rounded-2xl" 
                                    :disabled="!auth()->user()->can('settings.attendance.manage')"
                                />
                                @error('newGroup.name') <span class="text-[10px] text-red-500 font-bold px-2">{{ tr($message) }}</span> @enderror
                                
                                <x-ui.select 
                                    label="{{ tr('Policy Type') }}" 
                                    wire:model.live="newGroup.policy"
                                    required
                                    :disabled="!auth()->user()->can('settings.attendance.manage')"
                                >
                                    @foreach($policyTypes as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </x-ui.select>
                                @error('newGroup.policy') <span class="text-[10px] text-red-500 font-bold px-2">{{ tr($message) }}</span> @enderror
                            </div>

                            {{-- Tracking Mode (Only if NOT General) --}}
                            @if($newGroup['policy'] !== 'general')
                            <div class="space-y-3 animate-in fade-in slide-in-from-top-2 duration-300">
                                <label class="block text-[10px] font-bold text-indigo-500 uppercase tracking-widest">{{ tr('Specific Tracking Mode') }}</label>
                                <div class="grid grid-cols-3 gap-3">
                                    @foreach([
                                        'check_in_only' => ['icon' => 'fa-clock', 'title' => tr('Check-In Only')],
                                        'check_in_out' => ['icon' => 'fa-exchange-alt', 'title' => tr('In & Out')],
                                        'manual' => ['icon' => 'fa-edit', 'title' => tr('Manual')],
                                        'automatic' => ['icon' => 'fa-magic', 'title' => tr('Automatic')]
                                    ] as $mKey => $meta)
                                        <div 
                                            @can('settings.attendance.manage')
                                            wire:click="$set('newGroup.tracking_mode', '{{ $mKey }}')"
                                            class="p-3 border rounded-2xl cursor-pointer transition-all flex flex-col items-center text-center gap-2 {{ $newGroup['tracking_mode'] === $mKey ? 'border-purple-500 bg-purple-50 shadow-sm' : 'border-gray-50 bg-gray-50/50 hover:bg-white hover:border-purple-100' }}"
                                            @else
                                            class="p-3 border rounded-2xl cursor-not-allowed opacity-60 transition-all flex flex-col items-center text-center gap-2 {{ $newGroup['tracking_mode'] === $mKey ? 'border-purple-500 bg-purple-50' : 'border-gray-50 bg-gray-50/50' }}"
                                            @endcan
                                        >
                                            <div class="w-8 h-8 rounded-xl flex items-center justify-center {{ $newGroup['tracking_mode'] === $mKey ? 'bg-purple-500 text-white shadow-lg' : 'bg-white text-gray-400' }}">
                                                <i class="fas {{ $meta['icon'] }} text-xs"></i>
                                            </div>
                                            <div class="text-[10px] font-black {{ $newGroup['tracking_mode'] === $mKey ? 'text-purple-700' : 'text-gray-600' }}">{{ $meta['title'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                                @error('newGroup.tracking_mode') <span class="text-[10px] text-red-500 font-bold px-2 text-center block w-full">{{ tr($message) }}</span> @enderror
                            </div>
                            @endif

                            <x-ui.textarea 
                                label="{{ tr('Description') }}" 
                                wire:model.defer="newGroup.description" 
                                rows="2" 
                                placeholder="{{ tr('Describe the group purpose...') }}" 
                                class="!py-3 !rounded-2xl" 
                                :disabled="!auth()->user()->can('settings.attendance.manage')"
                            />
                            @error('newGroup.description') <span class="text-[10px] text-red-500 font-bold px-2">{{ tr($message) }}</span> @enderror
                        </div>
                    </div>

                    {{-- Preparation Methods Section --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-1 h-3 bg-blue-500 rounded-full"></span>
                            <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Verification Methods') }}</h4>
                        </div>
                        
                        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
                            <div class="grid grid-cols-3 gap-4">
                                @foreach([
                                    'gps' => ['icon' => 'fa-map-pin', 'color' => 'blue', 'label' => 'GPS'],
                                    'fingerprint' => ['icon' => 'fa-fingerprint', 'color' => 'indigo', 'label' => tr('Finger')],
                                    'nfc' => ['icon' => 'fa-wifi', 'color' => 'purple', 'label' => 'NFC']
                                ] as $mId => $cfg)
                                    @if($prepMethods[$mId]['enabled'] ?? false)
                                    <label class="relative flex flex-col items-center p-4 rounded-2xl border transition-all {{ in_array($mId, $newGroup['methods']) ? 'border-'.$cfg['color'].'-500 bg-'.$cfg['color'].'-50' : 'border-gray-50 bg-gray-50/50 hover:bg-white' }} {{ auth()->user()->can('settings.attendance.manage') ? 'cursor-pointer' : 'cursor-not-allowed opacity-60' }}">
                                        <input type="checkbox" wire:model.live="newGroup.methods" value="{{ $mId }}" class="sr-only" @cannot('settings.attendance.manage') disabled @endcannot>
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center mb-2 {{ in_array($mId, $newGroup['methods']) ? 'bg-'.$cfg['color'].'-500 text-white' : 'bg-white text-gray-400 shadow-sm' }}">
                                            <i class="fas {{ $cfg['icon'] }}"></i>
                                        </div>
                                        <span class="text-[10px] font-black uppercase tracking-tight {{ in_array($mId, $newGroup['methods']) ? 'text-'.$cfg['color'].'-700' : 'text-gray-500' }}">{{ $cfg['label'] }}</span>
                                        
                                        @if(in_array($mId, $newGroup['methods']))
                                        <div class="absolute top-2 right-2 w-4 h-4 bg-{{ $cfg['color'] }}-500 text-white rounded-full flex items-center justify-center text-[8px]">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        @endif
                                    </label>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right Column: Rules & Assignment --}}
                <div class="space-y-6">
                    {{-- Grace Periods Selection --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-1 h-3 bg-amber-500 rounded-full"></span>
                            <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Grace Periods Rules') }}</h4>
                        </div>

                        <div class="bg-amber-50/30 p-5 rounded-3xl border border-amber-100">
                            <div class="flex p-1 bg-white/50 rounded-xl mb-4 border border-amber-50">
                                <button 
                                    @can('settings.attendance.manage')
                                    wire:click="$set('newGroup.grace_periods_type', 'use_global')"
                                    class="flex-1 py-1.5 px-3 rounded-lg text-[10px] font-black transition-all cursor-pointer {{ $newGroup['grace_periods_type'] === 'use_global' ? 'bg-amber-500 text-white shadow-md' : 'text-amber-600 hover:bg-amber-50' }}"
                                    @else
                                    class="flex-1 py-1.5 px-3 rounded-lg text-[10px] font-black transition-all cursor-not-allowed {{ $newGroup['grace_periods_type'] === 'use_global' ? 'bg-amber-500 text-white opacity-60' : 'text-amber-400 opacity-60' }}"
                                    @endcan
                                >
                                    {{ tr('Company Defaults') }}
                                </button>
                                <button 
                                    @can('settings.attendance.manage')
                                    wire:click="$set('newGroup.grace_periods_type', 'custom')"
                                    class="flex-1 py-1.5 px-3 rounded-lg text-[10px] font-black transition-all cursor-pointer {{ $newGroup['grace_periods_type'] === 'custom' ? 'bg-amber-100 text-amber-700 shadow-sm' : 'text-amber-600 hover:bg-amber-50' }}"
                                    @else
                                    class="flex-1 py-1.5 px-3 rounded-lg text-[10px] font-black transition-all cursor-not-allowed {{ $newGroup['grace_periods_type'] === 'custom' ? 'bg-amber-50 text-amber-600 opacity-60' : 'text-amber-400 opacity-60' }}"
                                    @endcan
                                >
                                    {{ tr('Custom Per Group') }}
                                </button>
                            </div>

                            @if($newGroup['grace_periods_type'] === 'custom')
                                <div class="space-y-4 animate-in fade-in slide-in-from-right-3 duration-300">
                                    <div class="grid grid-cols-2 gap-4">
                                        <x-ui.input label="{{ tr('Late Arrival') }}" type="number" wire:model.defer="newGroup.custom_grace_periods.late_arrival" class="!py-2 !text-xs !rounded-xl" hint="{{ tr('Minutes') }}" :disabled="!auth()->user()->can('settings.attendance.manage')" />
                                        <x-ui.input label="{{ tr('Early Departure') }}" type="number" wire:model.defer="newGroup.custom_grace_periods.early_departure" class="!py-2 !text-xs !rounded-xl" hint="{{ tr('Minutes') }}" :disabled="!auth()->user()->can('settings.attendance.manage')" />
                                    </div>
                                    <x-ui.input label="{{ tr('Auto Checkout After') }}" type="number" wire:model.defer="newGroup.custom_grace_periods.auto_departure" class="!py-2 !text-xs !rounded-xl" hint="{{ tr('Minutes') }}" :disabled="!auth()->user()->can('settings.attendance.manage')" />
                                </div>
                            @else
                                <div class="py-6 text-center space-y-2">
                                    <p class="text-[10px] text-amber-500 font-bold italic">{{ tr('Following global company settings') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Employee Assignment Section --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-1 h-3 bg-indigo-500 rounded-full"></span>
                            <h4 class="text-xs font-black text-gray-700 uppercase tracking-wider">{{ tr('Member Assignment') }}</h4>
                        </div>

                        <div class="bg-indigo-50/30 rounded-3xl border border-indigo-100 overflow-hidden shadow-sm flex flex-col h-[280px]"
                            x-data="{ 
                                search: '', 
                                selected: @entangle('newGroup.employee_ids').live,
                                allEmployees: @js($availableEmployees),
                                toggle(id) {
                                    id = id.toString();
                                    if (this.selected.includes(id)) {
                                        this.selected = this.selected.filter(i => i !== id);
                                    } else {
                                        this.selected.push(id);
                                    }
                                }
                            }"
                        >
                            {{-- Search Header --}}
                            <div class="p-3 bg-white border-b border-indigo-50">
                                    <input 
                                    type="text" 
                                    x-model="search"
                                    class="w-full px-4 py-2 bg-gray-50 border border-gray-100 rounded-2xl text-[11px] focus:outline-none focus:border-indigo-400 focus:bg-white transition-all shadow-inner disabled:opacity-50"
                                    placeholder="{{ tr('Search employees...') }}"
                                    @cannot('settings.attendance.manage') disabled @endcannot
                                >
                            </div>

                            {{-- List --}}
                            <div class="flex-1 overflow-y-auto custom-scrollbar p-2">
                                <div class="grid grid-cols-1 gap-1">
                                    <template x-for="emp in allEmployees.filter(e => !search || e.name.toLowerCase().includes(search.toLowerCase()))" :key="emp.id">
                                        <div 
                                            @click="if (@js(auth()->user()->can('settings.attendance.manage'))) toggle(emp.id)"
                                            class="flex items-center justify-between px-4 py-2 rounded-2xl transition-all"
                                            :class="{
                                                'bg-indigo-600 text-white': selected.includes(emp.id.toString()),
                                                'hover:bg-white text-gray-700 cursor-pointer': !selected.includes(emp.id.toString()) && @js(auth()->user()->can('settings.attendance.manage')),
                                                'cursor-not-allowed opacity-60': @js(!auth()->user()->can('settings.attendance.manage'))
                                            }"
                                        >
                                            <span class="text-[11px] font-bold" x-text="emp.name"></span>
                                            <div x-show="selected.includes(emp.id.toString())">
                                                <i class="fas fa-check-circle text-xs"></i>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Footer Info --}}
                            <div class="p-3 bg-white/50 border-t border-indigo-50 flex items-center justify-between px-5">
                                <span class="text-[9px] font-black text-indigo-400 uppercase tracking-widest">{{ tr('Selected') }}</span>
                                <div class="px-2 py-0.5 rounded-full bg-indigo-600 text-white text-[10px] font-black" x-text="selected.length"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot:content>

    <x-slot:footer>
        <div class="flex items-center justify-end gap-3 w-full">
            <x-ui.secondary-button wire:click="$set('showGroupModal', false)" class="!text-xs !py-3 !px-6 !rounded-2xl cursor-pointer">
                {{ tr('Cancel') }}
            </x-ui.secondary-button>
            @can('settings.attendance.manage')
            <x-ui.primary-button 
                wire:click="saveGroup" 
                wire:loading.attr="disabled"
                class="!px-10 !rounded-2xl !text-xs !py-3 shadow-xl cursor-pointer"
            >
                <span wire:loading.remove wire:target="saveGroup">
                    <i class="fas fa-save me-1"></i>
                    {{ tr('Save Group') }}
                </span>
                <span wire:loading wire:target="saveGroup" class="flex items-center gap-2">
                    <i class="fas fa-spinner fa-spin"></i>
                    {{ tr('Saving...') }}
                </span>
            </x-ui.primary-button>
            @endcan
        </div>
    </x-slot:footer>
</x-ui.modal>



