<?php

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Athka\SystemSettings\Models\OfficialHolidayOccurrence;
use Athka\SystemSettings\Models\OfficialHolidayTemplate;
use Carbon\Carbon;

class AttendanceHolidays extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterCalendar = 'all'; 
    public string $filterStatus = 'all';  
    public string $filterDateStart = '';
    public string $filterDateEnd = '';

    public int $perPage = 10;

    public bool $createOpen = false;

    public string $companyCalendarType = 'gregorian';

    public string $newName = '';
    public string $newCalendarType = 'gregorian';
    public string $newStartDate = '';
    public int $newDurationDays = 1;

    public string $newGregorianAuto = '';
    public string $newDisplayHijriAuto = '';

    public bool $editOpen = false;
    public int $editOccurrenceId = 0;
    public int $editTemplateId = 0;

    public string $editName = '';
    public string $editStartDate = '';
    public int $editDurationDays = 1;


    public string $editGregorianAuto = '';
    public string $editDisplayHijriAuto = '';

    public bool $confirmDeleteOpen = false;
    public int $deleteOccurrenceId = 0;
    public string $deleteHolidayName = '';

    public function mount(): void
    {
        $type = (string) config('company.calendar_type', 'gregorian');
        $this->companyCalendarType = in_array($type, ['hijri', 'gregorian'], true) ? $type : 'gregorian';

        $this->newCalendarType = $this->companyCalendarType;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedNewStartDate($value): void
    {
        $this->syncCreateAutoDates();
    }

    protected function syncCreateAutoDates(): void
    {
        $this->newGregorianAuto = $this->newStartDate ?: '';

        $this->newDisplayHijriAuto = $this->hijriFromGregorian($this->newStartDate);
    }

    public function updatedEditStartDate($value): void
    {
        $this->syncEditAutoDates();
    }

    protected function syncEditAutoDates(): void
    {
        $this->editGregorianAuto = $this->editStartDate ?: '';
        $this->editDisplayHijriAuto = $this->hijriFromGregorian($this->editStartDate);
    }

    protected function hijriFromGregorian(?string $gregDate): string
    {
        if (! $gregDate) {
            return '';
        }

        try {
            $dt = Carbon::parse($gregDate)->startOfDay();
        } catch (\Throwable $e) {
            return '';
        }

        if (class_exists(\IntlDateFormatter::class)) {
            try {
                $fmt = new \IntlDateFormatter(
                    'en_US@calendar=islamic-umalqura',
                    \IntlDateFormatter::NONE,
                    \IntlDateFormatter::NONE,
                    $dt->getTimezone()->getName(),
                    \IntlDateFormatter::TRADITIONAL,
                    'yyyy/MM/dd'
                );

                $out = $fmt->format($dt);
                if (is_string($out) && preg_match('~^\d{4}/\d{2}/\d{2}$~', $out)) {
                    return $out;
                }
            } catch (\Throwable $e) {
            }
        }

        $expected = (int) $dt->year - 579; 
        $sec = (int) $dt->timestamp;

        if (
            class_exists(\Alkoumi\LaravelHijriDate\Hijri::class)
            && is_callable([\Alkoumi\LaravelHijriDate\Hijri::class, 'Date'])
        ) {
            try {
                $hSec = (string) \Alkoumi\LaravelHijriDate\Hijri::Date('Y/m/d', $sec);
                $hMs  = (string) \Alkoumi\LaravelHijriDate\Hijri::Date('Y/m/d', $sec * 1000);

                return $this->pickClosestHijri($hSec, $hMs, $expected) ?: $hSec;
            } catch (\Throwable $e) {
            }
        }

        if (
            class_exists(\GeniusTS\HijriDate\Hijri::class)
            && is_callable([\GeniusTS\HijriDate\Hijri::class, 'convertToHijri'])
        ) {
            try {
                $h = \GeniusTS\HijriDate\Hijri::convertToHijri($dt);
                return method_exists($h, 'format') ? (string) $h->format('Y/m/d') : '';
            } catch (\Throwable $e) {
            }
        }

        return '';
    }

    protected function pickClosestHijri(string $a, string $b, int $expectedYear): ?string
    {
        $ya = $this->extractHijriYear($a);
        $yb = $this->extractHijriYear($b);

        $aOk = ($ya >= 1200 && $ya <= 1700);
        $bOk = ($yb >= 1200 && $yb <= 1700);

        if ($aOk && $bOk) {
            return abs($ya - $expectedYear) <= abs($yb - $expectedYear) ? $a : $b;
        }
        if ($aOk) return $a;
        if ($bOk) return $b;

        return null;
    }

    protected function extractHijriYear(string $hijri): int
    {
        if (preg_match('~^(\d{4})[\/\-]~', $hijri, $m)) return (int) $m[1];
        if (preg_match('~(\d{4})$~', $hijri, $m)) return (int) $m[1];
        if (preg_match('~(\d{4})~', $hijri, $m)) return (int) $m[1];
        return 0;
    }

    public function clearAllFilters(): void
    {
        $this->search = '';
        $this->filterCalendar = 'all';
        $this->filterStatus = 'all';
        $this->filterDateStart = '';
        $this->filterDateEnd = '';
        $this->resetPage();
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

    public function getRowsProperty()
    {
        $q = OfficialHolidayOccurrence::query()->with('template');

        $companyId = $this->resolveCompanyId();
        if ($companyId > 0) {
            $q->where('company_id', $companyId);
        }

        if ($this->search !== '') {
            $q->whereHas('template', function ($qq) {
                $qq->where('name', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterCalendar !== 'all') {
            $q->whereHas('template', fn ($qq) => $qq->where('calendar_type', $this->filterCalendar));
        }

        if ($this->filterStatus !== 'all') {
            $isActive = $this->filterStatus === 'active';
            $q->whereHas('template', fn ($qq) => $qq->where('is_active', $isActive));
        }

        if ($this->filterDateStart !== '') {
            $q->whereDate('start_date', '>=', $this->filterDateStart);
        }

        if ($this->filterDateEnd !== '') {
            $q->whereDate('end_date', '<=', $this->filterDateEnd);
        }

        return $q->orderBy('start_date')->paginate($this->perPage);
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->createOpen = true;

        $this->newCalendarType = $this->companyCalendarType;

        $this->syncCreateAutoDates();
    }

    public function closeCreate(): void
    {
        $this->createOpen = false;

        $this->newName = '';
        $this->newCalendarType = $this->companyCalendarType;
        $this->newStartDate = '';
        $this->newDurationDays = 1;

        $this->newGregorianAuto = '';
        $this->newDisplayHijriAuto = '';
    }

    public function saveNewHoliday(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $companyId = $this->resolveCompanyId();
        if ($companyId <= 0) {
            session()->flash('error', tr('Company context not found'));
            return;
        }

        $this->newCalendarType = $this->companyCalendarType;

        $this->syncCreateAutoDates();

        $data = $this->validate([
            'newName' => ['required', 'string', 'max:255'],
            'newCalendarType' => ['required', 'in:hijri,gregorian'],
            'newStartDate' => ['required', 'date'],
            'newDurationDays' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        $template = OfficialHolidayTemplate::create([
            'company_id' => $companyId,
            'name' => $data['newName'],
            'calendar_type' => $data['newCalendarType'],
            'repeat_type' => 'once',
            'once_start_date' => $data['newStartDate'],
            'duration_days' => (int) $data['newDurationDays'],
            'is_active' => true,
        ]);

        $start = Carbon::parse($data['newStartDate'])->startOfDay();
        $end = (clone $start)->addDays(((int) $data['newDurationDays']) - 1);

        $displayHijri = $this->newDisplayHijriAuto ?: null;

        OfficialHolidayOccurrence::create([
            'company_id' => $companyId,
            'template_id' => $template->id,
            'year_greg' => (int) $start->year,
            'year_hijri' => null,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'duration_days' => (int) $data['newDurationDays'],
            'display_hijri' => $displayHijri,
            'is_tentative' => false,
            'is_overridden' => false,
        ]);

        session()->flash('success', tr('Saved successfully'));

        $this->closeCreate();
        $this->resetPage();
    }

    public function openEdit(int $occurrenceId): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $this->resetValidation();

        $row = OfficialHolidayOccurrence::query()
            ->with('template')
            ->find($occurrenceId);

        if (! $row) {
            session()->flash('error', tr('Record not found'));
            return;
        }

        $this->editOpen = true;

        $this->editOccurrenceId = (int) $row->id;
        $this->editTemplateId   = (int) ($row->template_id ?? 0);

        $this->editName = (string) ($row->template?->name ?? '');
        $this->editStartDate = (string) ($row->start_date ?? '');
        $this->editDurationDays = (int) ($row->duration_days ?? 1);

        $this->newCalendarType = $this->companyCalendarType;

        $this->syncEditAutoDates();
    }

    public function closeEdit(): void
    {
        $this->editOpen = false;

        $this->editOccurrenceId = 0;
        $this->editTemplateId = 0;

        $this->editName = '';
        $this->editStartDate = '';
        $this->editDurationDays = 1;

        $this->editGregorianAuto = '';
        $this->editDisplayHijriAuto = '';
    }

    public function saveEditHoliday(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        if ($this->editOccurrenceId <= 0 || $this->editTemplateId <= 0) {
            session()->flash('error', tr('Invalid record'));
            return;
        }

        $this->syncEditAutoDates();

        $data = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editStartDate' => ['required', 'date'],
            'editDurationDays' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        $companyId = $this->resolveCompanyId();

        DB::transaction(function () use ($data, $companyId) {
            $row = OfficialHolidayOccurrence::query()
                ->where('id', $this->editOccurrenceId)
                ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                ->lockForUpdate()
                ->first();

            if (! $row) {
                throw new \RuntimeException('Occurrence not found');
            }

            $template = OfficialHolidayTemplate::query()
                ->where('id', $this->editTemplateId)
                ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                ->lockForUpdate()
                ->first();

            if (! $template) {
                throw new \RuntimeException('Template not found');
            }

            $start = Carbon::parse($data['editStartDate'])->startOfDay();
            $end = (clone $start)->addDays(((int) $data['editDurationDays']) - 1);

            $template->update([
                'name' => $data['editName'],
                'calendar_type' => $this->companyCalendarType, 
                'repeat_type' => 'once',
                'once_start_date' => $start->toDateString(),
                'duration_days' => (int) $data['editDurationDays'],
            ]);

            $displayHijri = $this->editDisplayHijriAuto ?: null;

            $row->update([
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'duration_days' => (int) $data['editDurationDays'],
                'display_hijri' => $displayHijri,
            ]);
        });

        session()->flash('success', tr('Updated successfully'));

        $this->closeEdit();
        $this->resetPage();
    }

    public function confirmDelete(int $occurrenceId): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        $row = OfficialHolidayOccurrence::query()
            ->with('template')
            ->find($occurrenceId);

        if (! $row) {
            session()->flash('error', tr('Record not found'));
            return;
        }

        $this->confirmDeleteOpen = true;
        $this->deleteOccurrenceId = (int) $row->id;
        $this->deleteHolidayName = (string) ($row->template?->name ?? tr('Holiday'));
    }

    public function closeDelete(): void
    {
        $this->confirmDeleteOpen = false;
        $this->deleteOccurrenceId = 0;
        $this->deleteHolidayName = '';
    }

    public function deleteHoliday(): void
    {
        abort_unless(auth()->user()?->can('settings.attendance.manage'), 403);

        if ($this->deleteOccurrenceId <= 0) {
            session()->flash('error', tr('Invalid record'));
            return;
        }

        $companyId = $this->resolveCompanyId();

        DB::transaction(function () use ($companyId) {
            $row = OfficialHolidayOccurrence::query()
                ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                ->lockForUpdate()
                ->find($this->deleteOccurrenceId);

            if (! $row) {
                throw new \RuntimeException('Occurrence not found');
            }

            $templateId = (int) ($row->template_id ?? 0);

            $row->delete();

            if ($templateId > 0) {
                $remaining = OfficialHolidayOccurrence::query()
                    ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                    ->where('template_id', $templateId)
                    ->count();

                if ($remaining === 0) {
                    OfficialHolidayTemplate::query()
                        ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                        ->where('id', $templateId)
                        ->delete();
                }
            }
        });

        session()->flash('success', tr('Deleted successfully'));

        $this->closeDelete();
        $this->resetPage();
    }

    public function render()
    {
        return view('systemsettings::livewire.attendance.official-holidays', [
            'rows' => $this->rows,
        ])->layout('layouts.company-admin');
    }
}
