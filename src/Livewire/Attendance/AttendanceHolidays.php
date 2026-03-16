<?php

namespace Athka\SystemSettings\Livewire\Attendance;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Athka\SystemSettings\Models\OfficialHolidayOccurrence;
use Athka\SystemSettings\Services\HolidayService;

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
    public string $editCalendarType = 'gregorian';
    public string $editStartDate = '';
    public int $editDurationDays = 1;

    public string $editGregorianAuto = '';
    public string $editDisplayHijriAuto = '';


    protected HolidayService $holidayService;

    public function boot(HolidayService $holidayService): void
    {
        $this->holidayService = $holidayService;
    }

    public function mount(): void
    {
        $this->authorize('settings.attendance.view');
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
        $this->newDisplayHijriAuto = $this->holidayService->hijriFromGregorian($this->newStartDate);
    }

    public function updatedEditStartDate($value): void
    {
        $this->syncEditAutoDates();
    }

    protected function syncEditAutoDates(): void
    {
        $this->editGregorianAuto = $this->editStartDate ?: '';
        $this->editDisplayHijriAuto = $this->holidayService->hijriFromGregorian($this->editStartDate);
    }

    public function clearAllFilters(): void
    {
        $this->reset(['search', 'filterCalendar', 'filterStatus', 'filterDateStart', 'filterDateEnd']);
        $this->resetPage();
    }

    protected function resolveCompanyId(): int
    {
        $user = auth()->user();
        if (($id = (int) ($user->company_id ?? 0)) > 0) return $id;
        if (($id = (int) ($user->company?->id ?? 0)) > 0) return $id;

        foreach (['company_id', 'current_company_id', 'saas_company_id', 'current_saas_company_id'] as $key) {
            $val = session($key);
            if (is_numeric($val) && (int) $val > 0) return (int) $val;
            if (is_object($val) && isset($val->id) && (int) $val->id > 0) return (int) $val->id;
        }

        $host = request()->getHost();
        $slug = Str::before($host, '.');

        if (Schema::hasTable('saas_companies')) {
            if ($slug && ! in_array($slug, ['localhost', '127', 'www'], true)) {
                if ($found = DB::table('saas_companies')->where('slug', $slug)->value('id')) return (int) $found;
            }
            if ($found = DB::table('saas_companies')->where('primary_domain', $host)->value('id')) return (int) $found;
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
            $q->whereHas('template', fn ($qq) => $qq->where('name', 'like', '%' . $this->search . '%'));
        }

        if ($this->filterCalendar !== 'all') {
            $q->whereHas('template', fn ($qq) => $qq->where('calendar_type', $this->filterCalendar));
        }

        if ($this->filterStatus !== 'all') {
            $q->whereHas('template', fn ($qq) => $qq->where('is_active', $this->filterStatus === 'active'));
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
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();
        $this->createOpen = true;
        $this->newCalendarType = $this->companyCalendarType;
        $this->syncCreateAutoDates();
    }

    public function closeCreate(): void
    {
        $this->createOpen = false;
        $this->reset(['newName', 'newStartDate', 'newGregorianAuto', 'newDisplayHijriAuto']);
        $this->newCalendarType = $this->companyCalendarType;
        $this->newDurationDays = 1;
    }

    public function saveNewHoliday(): void
    {
        $this->authorize('settings.attendance.manage');

        if (($companyId = $this->resolveCompanyId()) <= 0) {
            $this->dispatch('toast', ['type' => 'error', 'message' => tr('Company context not found')]);
            return;
        }

        $this->syncCreateAutoDates();

        $data = $this->validate([
            'newName'         => ['required', 'string', 'max:255'],
            'newCalendarType' => ['required', 'in:hijri,gregorian'],
            'newStartDate'    => ['required', 'date'],
            'newDurationDays' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        $this->holidayService->createHoliday($companyId, $data, $this->newDisplayHijriAuto ?: null);

        $this->dispatch('toast', ['type' => 'success', 'message' => tr('Saved successfully')]);
        $this->closeCreate();
        $this->resetPage();
    }

    public function openEdit(int $occurrenceId): void
    {
        $this->authorize('settings.attendance.manage');
        $this->resetValidation();

        $row = OfficialHolidayOccurrence::with('template')->find($occurrenceId);
        if (! $row) {
            $this->dispatch('toast', ['type' => 'error', 'message' => tr('Record not found')]);
            return;
        }

        $this->editOpen = true;
        $this->editOccurrenceId = (int) $row->id;
        $this->editTemplateId   = (int) ($row->template_id ?? 0);
        $this->editName         = (string) ($row->template?->name ?? '');
        $this->editStartDate    = $row->start_date ? $row->start_date->format('Y-m-d') : '';
        $this->editDurationDays = (int) ($row->duration_days ?? 1);
        $this->editCalendarType = (string) ($row->template?->calendar_type ?? $this->companyCalendarType);

        $this->syncEditAutoDates();
    }

    public function closeEdit(): void
    {
        $this->editOpen = false;
        $this->reset([
            'editOccurrenceId', 'editTemplateId', 'editName', 'editStartDate', 
            'editGregorianAuto', 'editDisplayHijriAuto'
        ]);
        $this->editDurationDays = 1;
    }

    public function saveEditHoliday(): void
    {
        $this->authorize('settings.attendance.manage');

        if ($this->editOccurrenceId <= 0 || $this->editTemplateId <= 0) {
            $this->dispatch('toast', ['type' => 'error', 'message' => tr('Invalid record')]);
            return;
        }

        $this->syncEditAutoDates();

        $data = $this->validate([
            'editName'         => ['required', 'string', 'max:255'],
            'editCalendarType' => ['required', 'in:hijri,gregorian'],
            'editStartDate'    => ['required', 'date'],
            'editDurationDays' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        try {
            $this->holidayService->updateHoliday(
                $this->resolveCompanyId(), 
                $this->editOccurrenceId, 
                $this->editTemplateId, 
                $data, 
                $this->editDisplayHijriAuto ?: null
            );
            $this->dispatch('toast', ['type' => 'success', 'message' => tr('Updated successfully')]);
        } catch (\Exception $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => tr('Failed to update')]);
        }

        $this->closeEdit();
        $this->resetPage();
    }

    public function deleteHoliday(int $occurrenceId): void
    {
        $this->authorize('settings.attendance.manage');

        if ($occurrenceId <= 0) {
            $this->dispatch('toast', ['type' => 'error', 'message' => tr('Invalid record')]);
            return;
        }

        try {
            $this->holidayService->deleteHoliday($this->resolveCompanyId(), $occurrenceId);
            $this->dispatch('toast', ['type' => 'success', 'message' => tr('Deleted successfully')]);
        } catch (\Exception $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => tr('Failed to delete')]);
        }

        $this->resetPage();
    }

    public function exportCsv()
    {
        $this->authorize('settings.attendance.view');
        
        // This is a simplified CSV export logic
        $companyId = $this->resolveCompanyId();
        $q = OfficialHolidayOccurrence::query()->with('template');
        
        if ($companyId > 0) {
            $q->where('company_id', $companyId);
        }

        // Apply filters (matching index query)
        if ($this->search !== '') {
            $q->whereHas('template', fn ($qq) => $qq->where('name', 'like', '%' . $this->search . '%'));
        }
        if ($this->filterCalendar !== 'all') {
            $q->whereHas('template', fn ($qq) => $qq->where('calendar_type', $this->filterCalendar));
        }
        if ($this->filterStatus !== 'all') {
            $q->whereHas('template', fn ($qq) => $qq->where('is_active', $this->filterStatus === 'active'));
        }
        if ($this->filterDateStart !== '') {
            $q->whereDate('start_date', '>=', $this->filterDateStart);
        }
        if ($this->filterDateEnd !== '') {
            $q->whereDate('end_date', '<=', $this->filterDateEnd);
        }

        $records = $q->orderBy('start_date')->get();
        
        $filename = "official_holidays_" . now()->format('Y-m-d') . ".csv";
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = [tr('Name'), tr('Calendar'), tr('Start Date'), tr('End Date'), tr('Duration (days)'), tr('Status')];

        $callback = function() use($records, $columns) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($file, $columns);

            foreach ($records as $row) {
                fputcsv($file, [
                    $row->template?->name,
                    $row->template?->calendar_type,
                    $row->start_date?->format('Y-m-d'),
                    $row->end_date?->format('Y-m-d'),
                    $row->duration_days,
                    $row->template?->is_active ? tr('Active') : tr('Inactive'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function render()
    {
        return view('systemsettings::livewire.attendance.official-holidays', [
            'rows' => $this->rows,
        ])->layout('layouts.company-admin');
    }
}
