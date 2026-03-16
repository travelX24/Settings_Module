<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\OfficialHolidayOccurrence;
use Athka\SystemSettings\Models\OfficialHolidayTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HolidayService
{
    /**
     * Convert Gregorian date to Hijri string format (Y/m/d).
     */
    public function hijriFromGregorian(?string $gregDate): string
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

    private function pickClosestHijri(string $a, string $b, int $expectedYear): ?string
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

    private function extractHijriYear(string $hijri): int
    {
        if (preg_match('~^(\d{4})[\/\-]~', $hijri, $m)) return (int) $m[1];
        if (preg_match('~(\d{4})$~', $hijri, $m)) return (int) $m[1];
        if (preg_match('~(\d{4})~', $hijri, $m)) return (int) $m[1];
        return 0;
    }

    /**
     * Create a new official holiday template and occurrence.
     */
    public function createHoliday(int $companyId, array $data, ?string $displayHijri): void
    {
        DB::transaction(function () use ($companyId, $data, $displayHijri) {
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
        });
    }

    /**
     * Update an existing official holiday occurrence and its template.
     */
    public function updateHoliday(int $companyId, int $occurrenceId, int $templateId, array $data, ?string $displayHijri): void
    {
        DB::transaction(function () use ($companyId, $occurrenceId, $templateId, $data, $displayHijri) {
            $row = OfficialHolidayOccurrence::query()
                ->where('id', $occurrenceId)
                ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                ->lockForUpdate()
                ->first();

            if (! $row) {
                throw new \RuntimeException('Occurrence not found');
            }

            $template = OfficialHolidayTemplate::query()
                ->where('id', $templateId)
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
                'calendar_type' => $data['editCalendarType'],
                'repeat_type' => 'once',
                'once_start_date' => $start->toDateString(),
                'duration_days' => (int) $data['editDurationDays'],
            ]);

            $row->update([
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'duration_days' => (int) $data['editDurationDays'],
                'display_hijri' => $displayHijri,
            ]);
        });
    }

    /**
     * Delete an official holiday occurrence and its template if no other occurrences remain.
     */
    public function deleteHoliday(int $companyId, int $occurrenceId): void
    {
        DB::transaction(function () use ($companyId, $occurrenceId) {
            $row = OfficialHolidayOccurrence::query()
                ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                ->lockForUpdate()
                ->find($occurrenceId);

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
    }
}
