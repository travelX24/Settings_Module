<x-ui.modal wire:model="showGroupModal" maxWidth="4xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-lg border border-purple-100 shadow-sm"><i class="fas fa-users-cog"></i></div>
            <div>
                <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ $isEditingGroup ? tr('Edit Employee Group') : tr('Create New Group') }}</h3>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ tr('Attendance Policy Assignment') }}</p>
            </div>
        </div>
    </x-slot:title>
    <x-slot:content>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 py-2">
            {{-- Left Column: Configuration --}}
            <div class="lg:col-span-7 space-y-5">
                {{-- Group Identity --}}
                <div class="bg-gray-50/50 p-5 rounded-2xl border border-gray-100 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <x-ui.input label="{{ tr('Group Name') }}" wire:model.defer="newGroup.name" placeholder="{{ tr('e.g. Sales Team') }}" required class="!py-2" />
                        <x-ui.select label="{{ tr('Policy Type') }}" wire:model.live="newGroup.policy" required class="!py-2">
                            @foreach($policyTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    {{-- Specific Tracking Mode Selection --}}
                    @if($newGroup['policy'] === 'special')
                    <div class="space-y-2 animate-in fade-in zoom-in-95 duration-200">
                        <label class="block text-[9px] font-black text-indigo-400 uppercase tracking-widest">{{ tr('Tracking Mode') }}</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['check_in_only' => ['icon' => 'fa-clock', 'title' => tr('Attendance Only')], 'check_in_out' => ['icon' => 'fa-exchange-alt', 'title' => tr('Attendance & Departure')], 'manual' => ['icon' => 'fa-edit', 'title' => tr('Manual')]] as $mKey => $meta)
                                <div 
                                    wire:click="$set('newGroup.tracking_mode', '{{ $mKey }}')"
                                    class="p-2 border rounded-xl cursor-pointer transition-all flex items-center justify-center gap-2 {{ $newGroup['tracking_mode'] === $mKey ? 'border-indigo-500 bg-indigo-50 shadow-sm' : 'border-gray-100 bg-white hover:border-gray-200' }}"
                                >
                                    <div class="w-6 h-6 rounded-lg flex items-center justify-center {{ $newGroup['tracking_mode'] === $mKey ? 'bg-indigo-500 text-white' : 'bg-gray-50 text-gray-400' }}">
                                        <i class="fas {{ $meta['icon'] }} text-[10px]"></i>
                                    </div>
                                    <span class="text-[9px] font-bold {{ $newGroup['tracking_mode'] === $mKey ? 'text-indigo-700' : 'text-gray-500' }}">{{ $meta['title'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <x-ui.textarea label="{{ tr('Description') }}" wire:model.defer="newGroup.description" rows="2" placeholder="{{ tr('Describe the group purpose...') }}" class="!py-2" />
                </div>

                {{-- Preparation Methods --}}
                <div class="bg-white p-5 rounded-2xl border border-gray-100 space-y-3">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <i class="fas fa-fingerprint text-blue-400"></i>
                         {{ tr('Allowed Preparation Methods') }}
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        @if($prepMethods['gps']['enabled'])
                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all {{ in_array('gps', $newGroup['methods']) ? 'border-blue-500 bg-blue-50/30' : 'border-gray-100 bg-gray-50/30 hover:bg-gray-50' }}">
                            <input type="checkbox" wire:model="newGroup.methods" value="gps" class="w-4 h-4 text-blue-600 rounded border-gray-300">
                            <span class="text-xs font-bold text-gray-700">{{ tr('GPS') }}</span>
                        </label>
                        @endif

                        @if($prepMethods['fingerprint']['enabled'])
                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all {{ in_array('fingerprint', $newGroup['methods']) ? 'border-indigo-500 bg-indigo-50/30' : 'border-gray-100 bg-gray-50/30 hover:bg-gray-50' }}">
                            <input type="checkbox" wire:model="newGroup.methods" value="fingerprint" class="w-4 h-4 text-indigo-600 rounded border-gray-300">
                            <span class="text-xs font-bold text-gray-700">{{ tr('Finger') }}</span>
                        </label>
                        @endif

                        @if($prepMethods['nfc']['enabled'])
                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all {{ in_array('nfc', $newGroup['methods']) ? 'border-purple-500 bg-purple-50/30' : 'border-gray-100 bg-gray-50/30 hover:bg-gray-50' }}">
                            <input type="checkbox" wire:model="newGroup.methods" value="nfc" class="w-4 h-4 text-purple-600 rounded border-gray-300">
                            <span class="text-xs font-bold text-gray-700">{{ tr('NFC') }}</span>
                        </label>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Right Column: Rules & Assignment --}}
            <div class="lg:col-span-5 space-y-5">
                {{-- Grace Periods --}}
                <div class="bg-amber-50/30 p-5 rounded-2xl border border-amber-100/50 space-y-4">
                    <div class="flex items-center justify-between">
                        <label class="block text-[10px] font-black text-amber-600 uppercase tracking-widest">{{ tr('Grace Periods') }}</label>
                        <div class="flex gap-3">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="radio" wire:model.live="newGroup.grace_periods_type" value="general" class="w-3 h-3 text-amber-600">
                                <span class="text-[10px] font-bold text-gray-600 group-hover:text-amber-600">{{ tr('General') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="radio" wire:model.live="newGroup.grace_periods_type" value="custom" class="w-3 h-3 text-amber-600">
                                <span class="text-[10px] font-bold text-gray-600 group-hover:text-amber-600">{{ tr('Custom') }}</span>
                            </label>
                        </div>
                    </div>

                    @if($newGroup['grace_periods_type'] === 'custom')
                        <div class="grid grid-cols-1 gap-3 pt-1 animate-in slide-in-from-right-2 duration-200">
                            <div class="flex items-center gap-4">
                                <div class="flex-1"><x-ui.input label="{{ tr('Late (min)') }}" type="number" wire:model.defer="newGroup.custom_grace_periods.late_arrival" class="!py-1.5 !text-xs" /></div>
                                <div class="flex-1"><x-ui.input label="{{ tr('Early (min)') }}" type="number" wire:model.defer="newGroup.custom_grace_periods.early_departure" class="!py-1.5 !text-xs" /></div>
                            </div>
                            <x-ui.input label="{{ tr('Auto Checkout (min)') }}" type="number" wire:model.defer="newGroup.custom_grace_periods.auto_departure" class="!py-1.5 !text-xs" />
                        </div>
                    @else
                        <div class="py-1 text-center">
                            <p class="text-[10px] text-amber-400 italic">{{ tr('Following global company settings') }}</p>
                        </div>
                    @endif
                </div>

                {{-- Employee Assignment --}}
                <div class="p-0 rounded-2xl border border-blue-100 overflow-hidden bg-white shadow-sm">
                    <div class="px-5 py-3 bg-blue-50/50 border-b border-blue-100 flex items-center justify-between">
                        <label class="text-[10px] font-black text-blue-600 uppercase tracking-widest">{{ tr('Assign Employees') }}</label>
                        <span class="bg-blue-600 text-white text-[9px] px-2 py-0.5 rounded-full font-bold" x-text="$wire.newGroup.employee_ids.length"></span>
                    </div>
                    <div class="p-4" 
                        x-data="{ 
                            open: false, 
                            search: '', 
                            page: 1,
                            perPage: 5,
                            selected: @entangle('newGroup.employee_ids').live,
                            employees: @js($availableEmployees),
                            get filteredEmployees() {
                                if (!this.search) return this.employees;
                                return this.employees.filter(emp => 
                                    emp.name.toLowerCase().includes(this.search.toLowerCase())
                                );
                            },
                            get totalPages() {
                                return Math.ceil(this.filteredEmployees.length / this.perPage) || 1;
                            },
                            get paginatedEmployees() {
                                const start = (this.page - 1) * this.perPage;
                                return this.filteredEmployees.slice(start, start + this.perPage);
                            },
                            toggle(id) {
                                id = id.toString();
                                if (this.selected.includes(id)) {
                                    this.selected = this.selected.filter(i => i !== id);
                                } else {
                                    this.selected.push(id);
                                }
                            },
                            isSelected(id) {
                                return this.selected.includes(id.toString());
                            }
                        }"
                        x-init="$watch('search', () => page = 1)"
                    >
                        {{-- Search --}}
                        <div class="relative mb-3">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                            <input 
                                type="text" 
                                x-model="search"
                                class="w-full pl-8 pr-4 py-1.5 bg-gray-50 border border-gray-100 rounded-xl text-xs focus:outline-none focus:border-blue-400 focus:bg-white transition-all"
                                placeholder="{{ tr('Search...') }}"
                            >
                        </div>

                        {{-- Compact List --}}
                        <div class="space-y-1 min-h-[180px]">
                            <template x-for="emp in paginatedEmployees" :key="emp.id">
                                <div 
                                    @click="toggle(emp.id)"
                                    class="flex items-center justify-between px-3 py-1.5 rounded-lg cursor-pointer transition-all hover:bg-gray-50"
                                    :class="isSelected(emp.id) ? 'bg-blue-50 ring-1 ring-blue-100' : ''"
                                >
                                    <div class="flex items-center gap-2">
                                        <div 
                                            class="w-3.5 h-3.5 rounded border flex items-center justify-center transition-all"
                                            :class="isSelected(emp.id) ? 'bg-blue-600 border-blue-600' : 'border-gray-300 bg-white'"
                                        >
                                            <i x-show="isSelected(emp.id)" class="fas fa-check text-[7px] text-white"></i>
                                        </div>
                                        <span class="text-[11px]" :class="isSelected(emp.id) ? 'font-bold text-blue-800' : 'text-gray-600'" x-text="emp.name"></span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="filteredEmployees.length === 0">
                                <div class="py-8 text-center text-gray-300 text-[10px]">{{ tr('No results') }}</div>
                            </template>
                        </div>

                        {{-- Pagination --}}
                        <div x-show="totalPages > 1" class="mt-3 flex items-center justify-between border-t border-gray-50 pt-2">
                            <button type="button" @click="if(page > 1) page--" :disabled="page === 1" class="p-1 text-blue-500 disabled:opacity-20"><i class="fas fa-chevron-left rtl:rotate-180 text-[10px]"></i></button>
                            <span class="text-[9px] font-black text-gray-400" x-text="page + ' / ' + totalPages"></span>
                            <button type="button" @click="if(page < totalPages) page++" :disabled="page === totalPages" class="p-1 text-blue-500 disabled:opacity-20"><i class="fas fa-chevron-right rtl:rotate-180 text-[10px]"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot:content>

    <x-slot:footer>
        <div class="flex justify-between items-center w-full">
            <span class="text-[10px] text-gray-400 font-medium italic"><i class="fas fa-info-circle me-1"></i> {{ tr('Settings applied immediately to all group members.') }}</span>
            <div class="flex gap-3">
                <x-ui.secondary-button wire:click="$set('showGroupModal', false)" class="!text-xs !py-2">{{ tr('Cancel') }}</x-ui.secondary-button>
                <x-ui.brand-button wire:click="saveGroup" class="!px-8 !rounded-xl !text-xs !py-2 shadow-lg shadow-purple-100">
                    {{ $isEditingGroup ? tr('Update Group') : tr('Create Group') }}
                </x-ui.brand-button>
            </div>
        </div>
    </x-slot:footer>
</x-ui.modal>





