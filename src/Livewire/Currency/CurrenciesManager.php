<?php

namespace Athka\SystemSettings\Livewire\Currency;

use Athka\SystemSettings\Models\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\Intl\Currencies as IntlCurrencies;

class CurrenciesManager extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;

    public array $catalog = [];

    public bool $modalOpen = false;
    public string $mode = 'create';
    public ?int $editingId = null;

    public string $name = '';
    public string $symbol = '';
    public string $code = '';
    public bool $is_default = false;

    public bool $codeLocked = false;

    public bool $deleteConfirmOpen = false;
    public ?int $deletingId = null;

    public function mount(): void
    {
        $this->authorize('settings.currencies.manage');
        $this->catalog = $this->buildCurrencyCatalog();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    protected function companyId(): ?int
    {
        if (app()->bound('currentCompany') && app('currentCompany')) {
            return (int) app('currentCompany')->id;
        }

        $user = auth()->user();
        if ($user && !empty($user->saas_company_id)) {
            return (int) $user->saas_company_id;
        }

        return null;
    }

    protected function baseQuery()
    {
        return Currency::query()->where('company_id', $this->companyId());
    }

    protected function ensureCatalogLoaded(): void
    {
        if (empty($this->catalog)) {
            $this->catalog = $this->buildCurrencyCatalog();
        }
    }

    protected function rules(): array
    {
        $this->ensureCatalogLoaded();
        $companyId = $this->companyId();

        return [
            'code' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Za-z]{3}$/',
                Rule::in(array_keys($this->catalog)),
                Rule::unique('currencies', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($this->editingId),
            ],
            'is_default' => ['boolean'],
        ];
    }

    public function openCreate(): void
    {
        $this->authorize('settings.currencies.manage');
        $this->resetForm();
        $this->mode = 'create';
        $this->modalOpen = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorize('settings.currencies.manage');
        $c = $this->baseQuery()->findOrFail($id);

        $this->mode = 'edit';
        $this->editingId = $c->id;

        $this->code = (string) $c->code;
        $this->fillFromCode($this->code, fallbackName: $c->name, fallbackSymbol: $c->symbol);

        $this->is_default = (bool) $c->is_default;
        $this->codeLocked = $this->hasLinkedTransactions($c->id);

        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
    }

    public function updatedCode($value): void
    {
        $value = Str::upper(trim((string) $value));

        if ($this->mode === 'edit' && $this->codeLocked) {
            $this->code = (string) $this->baseQuery()->whereKey($this->editingId)->value('code');
            $this->fillFromCode($this->code, fallbackName: $this->name, fallbackSymbol: $this->symbol);
            return;
        }

        $this->fillFromCode($value);
    }

    public function save(): void
    {
        $this->authorize('settings.currencies.manage');
        $this->ensureCatalogLoaded();

        if ($this->mode === 'edit' && $this->codeLocked) {
            $existing = $this->baseQuery()->findOrFail($this->editingId);
            $this->code = (string) $existing->code;
            $this->fillFromCode($this->code, fallbackName: $existing->name, fallbackSymbol: $existing->symbol);
        } else {
            $this->code = Str::upper(trim($this->code));
            $this->fillFromCode($this->code);
        }

        $this->validate();

        DB::transaction(function () {
            $companyId = $this->companyId();

            $hasAny = $this->baseQuery()->exists();
            $forceDefault = !$hasAny && $this->mode === 'create';

            if ($this->is_default || $forceDefault) {
                $this->baseQuery()->update(['is_default' => false]);
            }

            if ($this->mode === 'create') {
                Currency::create([
                    'company_id' => $companyId,
                    'name' => $this->name,
                    'symbol' => $this->symbol,
                    'code' => $this->code,
                    'is_default' => $this->is_default || $forceDefault,
                ]);
            } else {
                $c = $this->baseQuery()->findOrFail($this->editingId);

                $c->update([
                    'name' => $this->name,
                    'symbol' => $this->symbol,
                    'code' => $this->code,
                    'is_default' => $this->is_default,
                ]);
            }

            if (!$this->baseQuery()->where('is_default', true)->exists()) {
                $first = $this->baseQuery()->orderBy('id')->first();
                if ($first) {
                    $first->update(['is_default' => true]);
                }
            }
        });
        
        $this->modalOpen = false;
        $this->resetPage(); // Reset to show newly created or edited items
        $this->resetForm();
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Saved successfully')]);
    }

    public function setDefault(int $id): void
    {
        $this->authorize('settings.currencies.manage');
        DB::transaction(function () use ($id) {
            $this->baseQuery()->update(['is_default' => false]);
            $this->baseQuery()->whereKey($id)->update(['is_default' => true]);
        });
        
        $this->resetPage(); // Ensure the default one is at the top if sorted
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Default currency updated')]);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('settings.currencies.manage');
        $this->deletingId = $id;
        $this->deleteConfirmOpen = true;

        // ✅ افتح الـ confirm-dialog عبر event (مطابق لـ id في Blade)
        $this->dispatch('open-confirm-currency-delete-confirm', id: $id);
    }


    public function cancelDelete(): void
    {
        $this->deleteConfirmOpen = false;
        $this->deletingId = null;
    }

    public function delete(): void
    {
        $this->authorize('settings.currencies.manage');
        $id = $this->deletingId;
        if (!$id) return;

        $c = $this->baseQuery()->findOrFail($id);

        if ($c->is_default) {
            $this->cancelDelete();
            $this->dispatch('toast', ['type' => 'error', 'message' => tr('Cannot delete default currency')]);
            return;
        }

        $count = (int) $this->baseQuery()->count();
        if ($count <= 1) {
            $this->cancelDelete();
            $this->dispatch('toast', ['type' => 'error', 'message' => tr('At least one currency must remain')]);
            return;
        }

        $linkedCount = $this->linkedTransactionsCount($c->id);
        if ($linkedCount > 0) {
            $this->cancelDelete();
            $this->dispatch('toast', ['type' => 'error', 'message' => tr('Cannot delete currency because it is linked to :count transaction(s)', ['count' => $linkedCount])]);
            return;
        }

        $c->delete();
        
        $this->resetPage(); // If we deleted an item, ensure we're not on an empty page
        $this->cancelDelete();
        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Deleted successfully')]);
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'symbol', 'code', 'is_default', 'codeLocked',
        ]);
        $this->mode = 'create';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    protected function buildCurrencyCatalog(): array
    {
        $locale = app()->isLocale('ar') ? 'ar' : 'en';

        if (class_exists(IntlCurrencies::class)) {
            try {
                $names = IntlCurrencies::getNames($locale);

                $out = [];
                foreach ($names as $code => $name) {
                    $symbol = IntlCurrencies::getSymbol($code, $locale) ?: $code;
                    $out[$code] = ['name' => $name, 'symbol' => $symbol];
                }

                if (!empty($out)) {
                    ksort($out);
                    return $out;
                }
            } catch (\Exception $e) {
                // Fallback
            }
        }

        // Robust Fallback with direct Arabic support
        $isAr = ($locale === 'ar');
        $fallback = [
            'USD' => ['name' => $isAr ? 'دولار أمريكي' : 'US Dollar', 'symbol' => '$'],
            'EUR' => ['name' => $isAr ? 'يورو' : 'Euro', 'symbol' => '€'],
            'SAR' => ['name' => $isAr ? 'ريال سعودي' : 'Saudi Riyal', 'symbol' => 'ر.س'],
            'YER' => ['name' => $isAr ? 'ريال يمني' : 'Yemeni Rial', 'symbol' => 'ر.ي'],
            'AED' => ['name' => $isAr ? 'درهم إماراتي' : 'UAE Dirham', 'symbol' => 'د.إ'],
            'EGP' => ['name' => $isAr ? 'جنيه مصري' : 'Egyptian Pound', 'symbol' => 'ج.م'],
            'GBP' => ['name' => $isAr ? 'جنيه إسترليني' : 'British Pound', 'symbol' => '£'],
            'KWD' => ['name' => $isAr ? 'دينار كويتي' : 'Kuwaiti Dinar', 'symbol' => 'د.ك'],
            'QAR' => ['name' => $isAr ? 'ريال قطري' : 'Qatari Rial', 'symbol' => 'ر.ق'],
            'BHD' => ['name' => $isAr ? 'دينار بحريني' : 'Bahraini Dinar', 'symbol' => 'د.ب'],
            'OMR' => ['name' => $isAr ? 'ريال عماني' : 'Omani Rial', 'symbol' => 'ر.ع'],
            'TRY' => ['name' => $isAr ? 'ليرة تركية' : 'Turkish Lira', 'symbol' => '₺'],
            'JOD' => ['name' => $isAr ? 'دينار أردني' : 'Jordanian Dinar', 'symbol' => 'د.أ'],
            'IQD' => ['name' => $isAr ? 'دينار عراقي' : 'Iraqi Dinar', 'symbol' => 'د.ع'],
            'LYD' => ['name' => $isAr ? 'دينار ليبي' : 'Libyan Dinar', 'symbol' => 'د.ل'],
            'DZD' => ['name' => $isAr ? 'دينار جزائري' : 'Algerian Dinar', 'symbol' => 'د.ج'],
            'MAD' => ['name' => $isAr ? 'درهم مغربي' : 'Moroccan Dirham', 'symbol' => 'د.م.'],
            'TND' => ['name' => $isAr ? 'دينار تونسي' : 'Tunisian Dinar', 'symbol' => 'د.ت'],
            'LBP' => ['name' => $isAr ? 'ليرة لبانية' : 'Lebanese Pound', 'symbol' => 'ل.ل'],
            'SYP' => ['name' => $isAr ? 'ليرة سورية' : 'Syrian Pound', 'symbol' => 'ل.س'],
            'JPY' => ['name' => $isAr ? 'ين ياباني' : 'Japanese Yen', 'symbol' => '¥'],
            'CNY' => ['name' => $isAr ? 'يوان صيني' : 'Chinese Yuan', 'symbol' => '¥'],
            'RUB' => ['name' => $isAr ? 'روبل روسي' : 'Russian Ruble', 'symbol' => '₽'],
        ];

        ksort($fallback);
        return $fallback;
    }

    protected function metaFor(string $code): ?array
    {
        $this->ensureCatalogLoaded();
        $code = Str::upper(trim($code));
        return $this->catalog[$code] ?? null;
    }

    protected function fillFromCode(string $code, ?string $fallbackName = null, ?string $fallbackSymbol = null): void
    {
        $code = Str::upper(trim($code));
        $meta = $this->metaFor($code);

        $this->code = $code;

        if ($meta) {
            $this->name = (string) $meta['name'];
            $this->symbol = (string) $meta['symbol'];
        } else {
            $this->name = (string) ($fallbackName ?? '');
            $this->symbol = (string) ($fallbackSymbol ?? '');
        }
    }

    protected function linkedTransactionsCount(int $currencyId): int
    {
        $checks = [
            ['table' => 'payrolls', 'column' => 'currency_id'],
            ['table' => 'loans', 'column' => 'currency_id'],
            ['table' => 'bonuses', 'column' => 'currency_id'],
            ['table' => 'transactions', 'column' => 'currency_id'],
            ['table' => 'wallet_transactions', 'column' => 'currency_id'],
            ['table' => 'payments', 'column' => 'currency_id'],
        ];

        $total = 0;

        foreach ($checks as $c) {
            $table = $c['table'];
            $column = $c['column'];

            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) continue;

            $total += (int) DB::table($table)->where($column, $currencyId)->count();
        }

        return $total;
    }

    protected function hasLinkedTransactions(int $currencyId): bool
    {
        return $this->linkedTransactionsCount($currencyId) > 0;
    }

    public function render()
    {
        $q = $this->baseQuery()
            ->when($this->search !== '', function ($query) {
                $s = '%' . $this->search . '%';
                $query->where(function ($qq) use ($s) {
                    $qq->where('name', 'like', $s)
                        ->orWhere('symbol', 'like', $s)
                        ->orWhere('code', 'like', $s);
                });
            })
            ->orderByDesc('is_default')
            ->orderBy('name');

        return view('systemsettings::livewire.currency.currencies-manager', [
            'currencies' => $q->paginate($this->perPage),
            'catalog' => $this->catalog,
        ])->layout('layouts.company-admin');
    }
}
