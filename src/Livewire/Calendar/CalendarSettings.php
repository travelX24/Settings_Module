<?php

namespace Athka\SystemSettings\Livewire\Calendar;

use Livewire\Component;
use Athka\SystemSettings\Models\OperationalCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CalendarSettings extends Component
{
    /**
     * The currently selected value in the UI (may be unsaved).
     */
    public string $calendar_type = 'gregorian'; // hijri | gregorian

    /**
     * The value stored in DB (the effective system-wide value).
     */
    public string $saved_calendar_type = 'gregorian';

    /**
     * Human readable last update time for UI (optional).
     */
    public ?string $saved_updated_human = null;

    public function mount(): void
    {
        $companyId = $this->resolveCompanyId();

        if ($companyId <= 0) {
            session()->flash('error', tr('Company context not found'));
            $this->redirectRoute('company-admin.settings.general', navigate: true);
            return;
        }

        $row = OperationalCalendar::query()->firstOrCreate(
            ['company_id' => $companyId],
            ['calendar_type' => 'gregorian']
        );

        $this->calendar_type = (string) ($row->calendar_type ?? 'gregorian');
        $this->saved_calendar_type = $this->calendar_type;
        $this->saved_updated_human = $row->updated_at?->diffForHumans();
    }

    public function save(): void
    {
        $companyId = $this->resolveCompanyId();

        if ($companyId <= 0) {
            session()->flash('error', tr('Company context not found'));
            $this->redirectRoute('company-admin.settings.general', navigate: true);
            return;
        }

        $data = $this->validate([
            'calendar_type' => ['required', 'in:hijri,gregorian'],
        ]);

        OperationalCalendar::updateOrCreate(
            ['company_id' => $companyId],
            ['calendar_type' => $data['calendar_type']]
        );

        // Keep UI state in sync with DB (so user sees what is actually saved)
        $this->saved_calendar_type = $data['calendar_type'];
        $this->saved_updated_human = now()->diffForHumans();
        $this->calendar_type = $data['calendar_type'];

        $this->toast(
            'success',
            tr('Saved successfully') . ' — ' . tr('This setting is applied across the whole system for this company.'),
            tr('Success')
        );

        // Fallback in case of redirect/navigation or missed events
        session()->flash('success', tr('Saved successfully'));
    }

    public function resetToSaved(): void
    {
        $this->calendar_type = $this->saved_calendar_type;

        $this->toast(
            'success',
            tr('Reverted to saved value.'),
            tr('Success'),
            2500
        );
    }

    protected function resolveCompanyId(): int
    {
        $user = auth()->user();

        $id = (int) ($user->company_id ?? 0);
        if ($id > 0) return $id;

        $id = (int) ($user->company?->id ?? 0);
        if ($id > 0) return $id;

        foreach (['company_id', 'current_company_id', 'saas_company_id', 'current_saas_company_id'] as $key) {
            $val = session($key);
            if (is_numeric($val) && (int) $val > 0) return (int) $val;
            if (is_object($val) && isset($val->id) && (int) $val->id > 0) return (int) $val->id;
        }

        $host = request()->getHost();
        $slug = Str::before($host, '.');

        if (Schema::hasTable('saas_companies')) {
            if ($slug && ! in_array($slug, ['localhost', '127', 'www'], true)) {
                $found = DB::table('saas_companies')->where('slug', $slug)->value('id');
                if ($found) return (int) $found;
            }

            $found = DB::table('saas_companies')->where('primary_domain', $host)->value('id');
            if ($found) return (int) $found;
        }

        return 0;
    }

    protected function toast(string $type, string $message, ?string $title = null, int $timeout = 3500): void
    {
        $payload = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'timeout' => $timeout,
        ];

        // Livewire v3
        if (method_exists($this, 'dispatch')) {
            $this->dispatch('toast', $payload);
            return;
        }

        // Livewire v2
        if (method_exists($this, 'dispatchBrowserEvent')) {
            $this->dispatchBrowserEvent('toast', $payload);
        }
    }

    public function render()
    {
        return view('systemsettings::livewire.calendar-settings')
            ->layout('layouts.company-admin');
    }
}
