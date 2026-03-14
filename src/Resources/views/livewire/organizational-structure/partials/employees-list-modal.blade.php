{{-- Global Employees List Modal --}}
<div
    x-data="{ 
        open: false, 
        employees: [], 
        loading: false, 
        type: '', 
        id: null,
        search: '',
        page: 1,
        perPage: 7,
        
        get filteredEmployees() {
            if (!this.search) return this.employees;
            const s = this.search.toLowerCase();
            return this.employees.filter(e => 
                e.name.toLowerCase().includes(s) || 
                e.job_title.toLowerCase().includes(s) ||
                e.department_name.toLowerCase().includes(s)
            );
        },
        
        get totalPages() {
            return Math.ceil(this.filteredEmployees.length / this.perPage);
        },
        
        get paginatedEmployees() {
            const start = (this.page - 1) * this.perPage;
            return this.filteredEmployees.slice(start, start + this.perPage);
        },

        reset() {
            this.search = '';
            this.page = 1;
        }
    }"
    x-on:open-employees-modal.window="
        open = true;
        type = $event.detail.type;
        id = $event.detail.id;
        loading = true;
        reset();
        const url = type === 'department'
            ? '/settings/organizational-structure/departments/employees/' + id
            : '/settings/organizational-structure/job-titles/employees/' + id;
        fetch(url)
            .then(res => res.json())
            .then(data => { employees = data; loading = false; })
            .catch(() => { loading = false; });
    "
    x-show="open"
    x-transition
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    @click.away="open = false"
    style="display: none;"
>
    <div
        @click.stop
        class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full flex flex-col overflow-hidden border border-gray-100"
    >
        {{-- Header --}}
        <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[color:var(--brand-via)]/10 flex items-center justify-center text-[color:var(--brand-via)]">
                        <i class="fas fa-users-viewfinder text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">{{ tr('Employees List') }}</h3>
                        <p class="text-xs text-gray-500" x-text="filteredEmployees.length + ' {{ tr('members found') }}'"></p>
                    </div>
                </div>
                <button @click="open = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            {{-- Search Box --}}
            <div class="mb-4">
                <x-ui.search-box 
                    x-model="search" 
                    @input="page = 1"
                    :model="null"
                    :placeholder="tr('Search by name, job, or department...')"
                />
            </div>
        </div>

        {{-- Content --}}
        <div class="p-6 flex-1 min-h-[400px]">
            <template x-if="loading">
                <div class="flex flex-col items-center justify-center py-20">
                    <div class="w-12 h-12 border-4 border-[color:var(--brand-via)]/20 border-t-[color:var(--brand-via)] rounded-full animate-spin"></div>
                    <p class="text-gray-500 mt-4 font-medium">{{ tr('Fetching data...') }}</p>
                </div>
            </template>

            <template x-if="!loading && filteredEmployees.length === 0">
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-user-slash text-gray-300 text-3xl"></i>
                    </div>
                    <p class="text-gray-900 font-bold text-lg">{{ tr('No results found') }}</p>
                    <p class="text-gray-500 text-sm mt-1">{{ tr('Try different keywords') }}</p>
                </div>
            </template>

            <template x-if="!loading && filteredEmployees.length > 0">
                <div class="space-y-3">
                    <template x-for="employee in paginatedEmployees" :key="employee.id">
                        <button
                            @click="$dispatch('open-employee-detail', { id: employee.id })"
                            class="group w-full text-start block p-4 bg-white border border-gray-100 rounded-2xl hover:border-[color:var(--brand-via)]/30 hover:bg-[color:var(--brand-via)]/[0.02] hover:shadow-md transition-all duration-200"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[color:var(--brand-via)]/10 to-[color:var(--brand-via)]/5 flex items-center justify-center text-[color:var(--brand-via)] group-hover:scale-110 transition-transform">
                                        <i class="fas fa-user text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 mb-0.5">
                                            <p class="font-extrabold text-gray-900" x-text="employee.name"></p>
                                            <span class="px-2 py-0.5 bg-gray-100 text-[9px] font-bold text-gray-500 rounded uppercase tracking-tighter" x-text="employee.department_name"></span>
                                        </div>
                                        <p class="text-xs font-medium text-gray-400 flex items-center gap-1.5">
                                            <i class="fas fa-briefcase text-[10px]"></i>
                                            <span x-text="employee.job_title"></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="hidden sm:inline-flex text-[10px] font-bold text-[color:var(--brand-via)] bg-[color:var(--brand-via)]/5 px-2.5 py-1.5 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity">{{ tr('View Profile') }}</span>
                                    <div class="w-8 h-8 rounded-full border border-gray-100 flex items-center justify-center text-gray-300 group-hover:text-[color:var(--brand-via)] group-hover:border-[color:var(--brand-via)]/20 transition-colors">
                                        <i class="fas fa-chevron-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }} text-xs"></i>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </template>
                </div>
            </template>
        </div>

        {{-- Footer / Pagination --}}
        <template x-if="totalPages > 1">
            <div class="px-6 py-4 border-t border-gray-50 bg-gray-50/50 flex items-center justify-between">
                <button 
                    @click="page--" 
                    :disabled="page === 1"
                    class="cursor-pointer flex items-center gap-2 px-4 py-2 text-sm font-bold rounded-xl transition-all"
                    :class="page === 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-700 hover:bg-white hover:shadow-sm'"
                >
                    <i class="fas fa-arrow-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}"></i>
                    {{ tr('Previous') }}
                </button>
                
                <div class="flex items-center gap-2">
                    <template x-for="p in totalPages" :key="p">
                        <button 
                            @click="page = p"
                            class="w-8 h-8 rounded-lg text-xs font-bold transition-all"
                            :class="page === p ? 'bg-[color:var(--brand-via)] text-white shadow-md' : 'text-gray-500 hover:bg-white'"
                            x-text="p"
                        ></button>
                    </template>
                </div>

                <button 
                    @click="page++" 
                    :disabled="page === totalPages"
                    class="cursor-pointer flex items-center gap-2 px-4 py-2 text-sm font-bold rounded-xl transition-all"
                    :class="page === totalPages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-700 hover:bg-white hover:shadow-sm'"
                >
                    {{ tr('Next') }}
                    <i class="fas fa-arrow-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }}"></i>
                </button>
            </div>
        </template>
    </div>
</div>





