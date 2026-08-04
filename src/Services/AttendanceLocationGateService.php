<?php

namespace Athka\SystemSettings\Services;

use Athka\Employees\Models\Employee;
use Athka\SystemSettings\Models\AttendanceGpsLocation;
use Athka\SystemSettings\Models\EmployeeGroup;
use Athka\SystemSettings\Support\GeofenceDecision;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceLocationGateService
{
    public const DEFAULT_MAX_ACCURACY_METERS = 50.0;

    public const DEFAULT_MAX_ONLINE_LOCATION_AGE_MINUTES = 5;

    public const DEFAULT_MAX_FUTURE_SKEW_MINUTES = 2;

    public function __construct(
        private readonly GeofenceService $geofenceService
    ) {
    }

    public function settings(): array
    {
        return [
            'max_accuracy_meters' => $this->maxAccuracyMeters(),
            'max_online_location_age_minutes' => $this->maxOnlineLocationAgeMinutes(),
            'max_future_skew_minutes' => $this->maxFutureSkewMinutes(),
            'offline_boundary_policy' => 'current_boundaries_at_sync',
        ];
    }

    public function allowedLocationsForEmployee(
        int $companyId,
        Employee $employee
    ): Collection {
        $groupIds = EmployeeGroup::query()
            ->where('saas_company_id', $companyId)
            ->whereHas(
                'employees',
                fn ($query) => $query->where(
                    'employees.id',
                    (int) $employee->id
                )
            )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $branchId = (int) (
            $employee->branch_id
            ?? $employee->department_id
            ?? 0
        );

        return AttendanceGpsLocation::query()
            ->where('saas_company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(function (AttendanceGpsLocation $location) use (
                $groupIds,
                $branchId
            ): bool {
                $groupMatches = $location->employee_group_id === null
                    || in_array(
                        (int) $location->employee_group_id,
                        $groupIds,
                        true
                    );

                $branchMatches = $location->branch_id === null
                    || (
                        $branchId > 0
                        && (int) $location->branch_id === $branchId
                    );

                return $groupMatches && $branchMatches;
            })
            ->values();
    }

    public function evaluateOnline(
        int $companyId,
        Employee $employee,
        array $payload,
        ?array $allowedLocationIds = null,
    ): GeofenceDecision {
        $validation = $this->validateLocationPayload(
            payload: $payload,
            requireRecentCapture: true,
        );

        if ($validation !== null) {
            return $validation;
        }

        $ids = $allowedLocationIds
            ?? $this->allowedLocationsForEmployee($companyId, $employee)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        return $this->geofenceService
            ->evaluate(
                (float) $payload['lat'],
                (float) $payload['lng'],
                $ids
            )
            ->withCaptureMeta(
                (float) $payload['gps_accuracy'],
                Carbon::parse($payload['location_captured_at'])
                    ->toIso8601String()
            );
    }

    public function evaluateOffline(
        int $companyId,
        Employee $employee,
        array $payload,
    ): GeofenceDecision {
        $validation = $this->validateLocationPayload(
            payload: $payload,
            requireRecentCapture: false,
        );

        if ($validation !== null) {
            return $validation;
        }

        $ids = $this->allowedLocationsForEmployee($companyId, $employee)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->geofenceService
            ->evaluate(
                (float) $payload['lat'],
                (float) $payload['lng'],
                $ids
            )
            ->withCaptureMeta(
                (float) $payload['gps_accuracy'],
                Carbon::parse($payload['location_captured_at'])
                    ->toIso8601String()
            );
    }

    private function validateLocationPayload(
        array $payload,
        bool $requireRecentCapture,
    ): ?GeofenceDecision {
        if (! empty($payload['is_mocked'])) {
            return GeofenceDecision::deny(
                code: 'fake_location_detected',
                message: $this->message(
                    'تم اكتشاف موقع وهمي. أوقف تطبيقات تغيير الموقع وحاول مرة أخرى.',
                    'Fake location detected. Disable mock-location apps and try again.'
                ),
                httpStatus: 403,
            );
        }

        if (! is_numeric($payload['lat'] ?? null)
            || ! is_numeric($payload['lng'] ?? null)) {
            return GeofenceDecision::deny(
                code: 'gps_coordinates_required',
                message: $this->message(
                    'تعذر قراءة إحداثيات موقعك. حاول تحديد الموقع مرة أخرى.',
                    'GPS coordinates are required.'
                ),
                httpStatus: 422,
            );
        }

        $lat = (float) $payload['lat'];
        $lng = (float) $payload['lng'];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return GeofenceDecision::deny(
                code: 'gps_coordinates_invalid',
                message: $this->message(
                    'إحداثيات الموقع غير صالحة.',
                    'GPS coordinates are invalid.'
                ),
                httpStatus: 422,
            );
        }

        if (! is_numeric($payload['gps_accuracy'] ?? null)) {
            return GeofenceDecision::deny(
                code: 'gps_accuracy_required',
                message: $this->message(
                    'تعذر معرفة دقة موقعك. حاول مرة أخرى.',
                    'GPS accuracy is required.'
                ),
                httpStatus: 422,
            );
        }

        $accuracy = (float) $payload['gps_accuracy'];

        if ($accuracy < 0 || $accuracy > $this->maxAccuracyMeters()) {
            $limit = (int) round($this->maxAccuracyMeters());

            return GeofenceDecision::deny(
                code: 'gps_accuracy_too_low',
                message: $this->message(
                    "دقة الموقع الحالية غير كافية. يجب أن تكون الدقة {$limit} مترًا أو أفضل.",
                    "Location accuracy must be {$limit} meters or better."
                ),
                httpStatus: 422,
            )->withCaptureMeta($accuracy, null);
        }

        if (empty($payload['location_captured_at'])) {
            return GeofenceDecision::deny(
                code: 'location_captured_at_required',
                message: $this->message(
                    'وقت التقاط الموقع مطلوب.',
                    'The location capture time is required.'
                ),
                httpStatus: 422,
            )->withCaptureMeta($accuracy, null);
        }

        try {
            $capturedAt = Carbon::parse($payload['location_captured_at']);
        } catch (\Throwable) {
            return GeofenceDecision::deny(
                code: 'location_captured_at_invalid',
                message: $this->message(
                    'وقت التقاط الموقع غير صالح.',
                    'The location capture time is invalid.'
                ),
                httpStatus: 422,
            )->withCaptureMeta($accuracy, null);
        }

        if ($capturedAt->greaterThan(
            Carbon::now()->addMinutes($this->maxFutureSkewMinutes())
        )) {
            return GeofenceDecision::deny(
                code: 'location_capture_time_in_future',
                message: $this->message(
                    'وقت الجهاز غير متزامن مع وقت النظام.',
                    'The device time is ahead of the server time.'
                ),
                httpStatus: 422,
            )->withCaptureMeta(
                $accuracy,
                $capturedAt->toIso8601String()
            );
        }

        if ($requireRecentCapture
            && $capturedAt->lessThan(
                Carbon::now()->subMinutes($this->maxOnlineLocationAgeMinutes())
            )) {
            return GeofenceDecision::deny(
                code: 'stale_location',
                message: $this->message(
                    'الموقع المرسل قديم. حدّث موقعك وحاول مرة أخرى.',
                    'The submitted location is stale. Refresh it and try again.'
                ),
                httpStatus: 422,
            )->withCaptureMeta(
                $accuracy,
                $capturedAt->toIso8601String()
            );
        }

        return null;
    }

    private function maxAccuracyMeters(): float
    {
        return $this->configFloat(
            'attendance.location_gate.max_accuracy_meters',
            self::DEFAULT_MAX_ACCURACY_METERS
        );
    }

    private function maxOnlineLocationAgeMinutes(): int
    {
        return $this->configInt(
            'attendance.location_gate.max_online_location_age_minutes',
            self::DEFAULT_MAX_ONLINE_LOCATION_AGE_MINUTES
        );
    }

    private function maxFutureSkewMinutes(): int
    {
        return $this->configInt(
            'attendance.location_gate.max_future_skew_minutes',
            self::DEFAULT_MAX_FUTURE_SKEW_MINUTES
        );
    }

    private function configFloat(string $key, float $default): float
    {
        try {
            return max(1.0, (float) config($key, $default));
        } catch (\Throwable) {
            return $default;
        }
    }

    private function configInt(string $key, int $default): int
    {
        try {
            return max(1, (int) config($key, $default));
        } catch (\Throwable) {
            return $default;
        }
    }

    private function message(string $arabic, string $english): string
    {
        try {
            return app()->getLocale() === 'ar' ? $arabic : $english;
        } catch (\Throwable) {
            return $english;
        }
    }
}
