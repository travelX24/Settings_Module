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
        $this->authorize('settings.calendar.manage');
        $companyId = $this->resolveCompanyId();

        if ($companyId <= 0) {
            session()->flash('error', tr('Company context not found'));
            $this->redirectRoute('company-admin.settings.general', navigate: true);
            return;
        }

        $row = OperationalCalendar::query()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'calendar_type' => 'gregorian',
                'working_days' => json_encode([6, 0, 1, 2, 3]) // السبت-الخميس
            ]
        );

        $this->calendar_type = (string) ($row->calendar_type ?? 'gregorian');
        $this->saved_calendar_type = $this->calendar_type;
        $this->saved_updated_human = $row->updated_at?->diffForHumans();
    }

    public function save(): void
    {
        $this->authorize('settings.calendar.manage');
        $companyId = $this->resolveCompanyId();

        if ($companyId <= 0) {
            session()->flash('error', tr('Company context not found'));
            $this->redirectRoute('company-admin.settings.general', navigate: true);
            return;
        }

        $data = $this->validate([
            'calendar_type' => ['required', 'in:hijri,gregorian'],
        ]);

        // ✅ تم إيقاف عملية التحويل التلقائي للسنوات لتجنب التضارب وفقدان البيانات
        // السنوات ستبقى كما هي في قاعدة البيانات، وسيتم فلترتها برمجياً حسب نوع التقويم المختار
        $this->executeCalendarChange($data['calendar_type']);
    }

    private function performCalendarConversion(string $newType): void
    {
        $companyId = $this->resolveCompanyId();
        
        $years = \Athka\SystemSettings\Models\LeavePolicyYear::where('company_id', $companyId)->get();
        
        foreach ($years as $year) {
            $oldVal = (int) $year->year;
            $newVal = $this->convertYearValue($oldVal, $this->saved_calendar_type, $newType);
            
            // Generate valid starts_on and ends_on loosely based on new type
            if ($newType === 'hijri') {
                $startsOn = $newVal . "-01-01";
                $endsOn = $newVal . "-12-29";
            } else {
                $startsOn = $newVal . "-01-01";
                $endsOn = $newVal . "-12-31";
            }
            
            // Handle uniqueness to prevent duplications if any
            while (\Athka\SystemSettings\Models\LeavePolicyYear::where('company_id', $companyId)->where('year', $newVal)->where('id', '!=', $year->id)->exists()) {
                $newVal++;
                if ($newType === 'hijri') {
                    $startsOn = $newVal . "-01-01";
                    $endsOn = $newVal . "-12-29";
                } else {
                    $startsOn = $newVal . "-01-01";
                    $endsOn = $newVal . "-12-31";
                }
            }
            
            $year->update([
                'year' => $newVal,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);
        }

        $this->executeCalendarChange($newType);
    }
    
    private function convertYearValue(int $year, string $from, string $to): int
    {
        if ($from === 'gregorian' && $to === 'hijri') {
            if ($year >= 1900 && $year <= 2500) {
                if (class_exists(\IntlCalendar::class)) {
                    $tz = \IntlTimeZone::createTimeZone('UTC');
                    $cal = \IntlCalendar::createInstance($tz, 'en_US@calendar=islamic-umalqura');
                    $greg = \IntlCalendar::createInstance($tz, 'en_US@calendar=gregorian');
                    $greg->set($year, 0, 1);
                    $cal->setTime($greg->getTime());
                    return (int) $cal->get(\IntlCalendar::FIELD_YEAR);
                }
                return (int) round(($year - 622) * 33 / 32); 
            }
        } elseif ($from === 'hijri' && $to === 'gregorian') {
            if ($year >= 1300 && $year <= 1600) {
                 if (class_exists(\IntlCalendar::class)) {
                     $tz = \IntlTimeZone::createTimeZone('UTC');
                     $cal = \IntlCalendar::createInstance($tz, 'en_US@calendar=islamic-umalqura');
                     $cal->set(\IntlCalendar::FIELD_YEAR, $year);
                     $cal->set(\IntlCalendar::FIELD_MONTH, 0); 
                     $cal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, 1); 
                     
                     $greg = \IntlCalendar::createInstance($tz, 'en_US@calendar=gregorian');
                     $greg->setTime($cal->getTime());
                     return (int) $greg->get(\IntlCalendar::FIELD_YEAR);
                 }
                 return (int) round(($year * 32 / 33) + 622);
            }
        }
        
        return $year;
    }

    private function executeCalendarChange(string $type): void
    {
        $companyId = $this->resolveCompanyId();

        OperationalCalendar::updateOrCreate(
            ['company_id' => $companyId],
            [
                'calendar_type' => $type,
                'working_days' => json_encode([6, 0, 1, 2, 3]) // السبت-الخميس
            ]
        );

        // ✅ مسح جميع مفاتيح كاش نوع التقويم المستخدمة حالياً لضمان انعكاس التغيير فوراً
        cache()->forget("company_{$companyId}_calendar_type");
        cache()->forget("company_calendar_type_{$companyId}");

        // Keep UI state in sync with DB (so user sees what is actually saved)
        $this->saved_calendar_type = $type;
        $this->saved_updated_human = now()->diffForHumans();
        $this->calendar_type = $type;

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
        if ($id > 0)
            return $id;

        $id = (int) ($user->company?->id ?? 0);
        if ($id > 0)
            return $id;

        foreach (['company_id', 'current_company_id', 'saas_company_id', 'current_saas_company_id'] as $key) {
            $val = session($key);
            if (is_numeric($val) && (int) $val > 0)
                return (int) $val;
            if (is_object($val) && isset($val->id) && (int) $val->id > 0)
                return (int) $val->id;
        }

        $host = request()->getHost();
        $slug = Str::before($host, '.');

        if (Schema::hasTable('saas_companies')) {
            if ($slug && !in_array($slug, ['localhost', '127', 'www'], true)) {
                $found = DB::table('saas_companies')->where('slug', $slug)->value('id');
                if ($found)
                    return (int) $found;
            }

            $found = DB::table('saas_companies')->where('primary_domain', $host)->value('id');
            if ($found)
                return (int) $found;
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
