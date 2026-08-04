<?php

namespace Athka\SystemSettings\Livewire\Attendance\Traits;

use Athka\SystemSettings\Models\AttendanceGpsLocation;
use Athka\SystemSettings\Services\GeocodingService;

trait HandleGpsSettings
{
    public $gpsData = [
        'name' => '',
        'lat' => '',
        'lng' => '',
        'radius' => 100,
        'geofence_type' => AttendanceGpsLocation::GEOFENCE_TYPE_CIRCLE,
        'boundary_geojson' => '',
        'is_active' => true,
        'address' => '',
        'country' => '',
        'city' => '',
        'region' => ''
    ];

    public function reverseGeocode($lat, $lng): ?array
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return app(GeocodingService::class)->reverse(
            (float) $lat,
            (float) $lng
        );
    }

    public function searchLocation(
        $query,
        $lat = null,
        $lng = null,
        $city = null,
        $country = null,
        $bounds = null
    ): array {
        return app(GeocodingService::class)->search(
            trim((string) $query),
            is_numeric($lat) ? (float) $lat : null,
            is_numeric($lng) ? (float) $lng : null,
            is_string($city) ? $city : null,
            is_string($country) ? $country : null,
            is_array($bounds) ? $bounds : null
        );
    }

    public function openGpsModal($id = null)
    {
        $this->authorizeManage();
        $this->resetValidation();

        $companyId = auth()->user()->saas_company_id;
        if ($id) {
            $loc = AttendanceGpsLocation::where('saas_company_id', $companyId)->findOrFail($id);
            $this->selectedId = $id;
            $this->isEditing = true;
            $this->gpsData = [
                'name' => $loc->name,
                'lat' => $loc->lat,
                'lng' => $loc->lng,
                'radius' => $loc->radius_meters,
                'geofence_type' => $loc->geofence_type ?: AttendanceGpsLocation::GEOFENCE_TYPE_CIRCLE,
                'boundary_geojson' => $loc->boundary_geojson
                    ? json_encode($loc->boundary_geojson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : '',
                'is_active' => (bool)$loc->is_active,
                'address' => $loc->address_text ?? '',
                'country' => $loc->country ?? '',
                'city' => $loc->city ?? '',
                'region' => $loc->region ?? ''
            ];

            // Set target selections
            if ($loc->branch_id) {
                $this->gpsTarget = 'branch';
                $this->selectedBranch = $loc->branch_id;
            } else {
                $this->gpsTarget = 'groups';
                $this->selectedGroups = [$loc->employee_group_id];
            }
        } else {
            $this->selectedId = null;
            $this->isEditing = false;
            $this->gpsData = [
                'name' => '',
                'lat' => '',
                'lng' => '',
                'radius' => 100,
                'geofence_type' => AttendanceGpsLocation::GEOFENCE_TYPE_CIRCLE,
                'boundary_geojson' => '',
                'is_active' => true,
                'address' => '',
                'country' => '',
                'city' => '',
                'region' => ''
            ];
        }
        $this->showGpsModal = true;
    }

    public function validationAttributes()
    {
        return [
            'gpsData.name' => tr('Location Name'),
            'gpsData.lat' => tr('Latitude'),
            'gpsData.lng' => tr('Longitude'),
            'gpsData.radius' => tr('Radius'),
            'gpsData.geofence_type' => tr('Geofence Type'),
            'gpsData.boundary_geojson' => tr('Geographic Boundary'),
            'selectedBranch' => tr('Branch'),
            'selectedGroups' => tr('Employee Groups'),
        ];
    }

    public function saveGpsLocation()
    {
        $this->authorizeManage();

        $rules = [
            'gpsData.name' => ['required', 'string', 'min:3'],
            'gpsData.lat' => ['required', 'numeric', 'between:-90,90'],
            'gpsData.lng' => ['required', 'numeric', 'between:-180,180'],
            'gpsData.geofence_type' => ['required', 'in:circle,polygon'],
            'gpsData.radius' => ['required_if:gpsData.geofence_type,circle', 'nullable', 'integer', 'min:10', 'max:10000'],
            'gpsData.boundary_geojson' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (($this->gpsData['geofence_type'] ?? AttendanceGpsLocation::GEOFENCE_TYPE_CIRCLE)
                        !== AttendanceGpsLocation::GEOFENCE_TYPE_POLYGON) {
                        return;
                    }

                    if (! $this->decodeAndValidateBoundary($value)) {
                        $fail(tr('Draw a valid geographic boundary on the map.'));
                    }
                },
            ],
        ];

        if ($this->gpsTarget === 'branch') {
            $rules['selectedBranch'] = 'required';
        } else {
            $rules['selectedGroups'] = 'required|array|min:1';
        }

        $this->validate($rules);

        $geofenceType = $this->gpsData['geofence_type'];
        $boundary = $geofenceType === AttendanceGpsLocation::GEOFENCE_TYPE_POLYGON
            ? $this->decodeAndValidateBoundary($this->gpsData['boundary_geojson'])
            : null;

        $saveData = [
            'name' => $this->gpsData['name'],
            'lat' => $this->gpsData['lat'],
            'lng' => $this->gpsData['lng'],
            'radius_meters' => (int) ($this->gpsData['radius'] ?: 100),
            'geofence_type' => $geofenceType,
            'boundary_geojson' => $boundary,
            'address_text' => $this->gpsData['address'],
            'country' => $this->gpsData['country'] ?: null,
            'city' => $this->gpsData['city'] ?: null,
            'region' => $this->gpsData['region'] ?: null,
            'is_active' => $this->gpsData['is_active'],
            'created_by_user_id' => auth()->id(),
        ];

        if ($this->gpsTarget === 'branch') {
            $saveData['branch_id'] = ($this->selectedBranch === 'main') ? null : $this->selectedBranch;
            $saveData['employee_group_id'] = null;
        } else {
            // Note: DB schema shows singular employee_group_id.
            // Taking the first one if multiple are selected, or null if none.
            $saveData['employee_group_id'] = is_array($this->selectedGroups) ? ($this->selectedGroups[0] ?? null) : $this->selectedGroups;
            $saveData['branch_id'] = null;
        }

        $this->attendanceSettingService->saveGpsLocation(
            auth()->user()->saas_company_id,
            $saveData,
            $this->selectedId
        );

        $this->showGpsModal = false;
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('GPS location saved successfully.'));
    }

    private function decodeAndValidateBoundary(mixed $value): ?array
    {
        if (is_array($value)) {
            $geometry = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $geometry = json_decode($value, true);
        } else {
            return null;
        }

        if (! is_array($geometry)) {
            return null;
        }

        if (($geometry['type'] ?? null) === 'Feature') {
            $geometry = $geometry['geometry'] ?? null;
        }

        if (! is_array($geometry) || ($geometry['type'] ?? null) !== 'Polygon') {
            return null;
        }

        $rings = $geometry['coordinates'] ?? null;

        if (! is_array($rings) || $rings === []) {
            return null;
        }

        $normalizedRings = [];

        foreach ($rings as $ring) {
            $normalizedRing = $this->normalizeBoundaryRing($ring);

            if (! $normalizedRing) {
                return null;
            }

            $normalizedRings[] = $normalizedRing;
        }

        return [
            'type' => 'Polygon',
            'coordinates' => $normalizedRings,
        ];
    }

    private function normalizeBoundaryRing(mixed $ring): ?array
    {
        if (! is_array($ring) || count($ring) < 4) {
            return null;
        }

        $normalized = [];

        foreach ($ring as $point) {
            if (! is_array($point)
                || count($point) < 2
                || ! is_numeric($point[0])
                || ! is_numeric($point[1])) {
                return null;
            }

            $lng = (float) $point[0];
            $lat = (float) $point[1];

            if ($lng < -180 || $lng > 180 || $lat < -90 || $lat > 90) {
                return null;
            }

            $normalized[] = [$lng, $lat];
        }

        $first = $normalized[0];
        $last = $normalized[array_key_last($normalized)];

        if (abs($first[0] - $last[0]) > 1.0E-10
            || abs($first[1] - $last[1]) > 1.0E-10) {
            return null;
        }

        $uniquePoints = array_unique(array_map(
            fn (array $point): string => sprintf('%.12F,%.12F', $point[0], $point[1]),
            array_slice($normalized, 0, -1)
        ));

        if (count($uniquePoints) < 3
            || abs($this->signedBoundaryRingArea($normalized)) <= 1.0E-14
            || $this->boundaryRingHasSelfIntersections($normalized)) {
            return null;
        }

        return $normalized;
    }

    private function signedBoundaryRingArea(array $ring): float
    {
        $area = 0.0;

        for ($index = 0, $lastIndex = count($ring) - 1; $index < $lastIndex; $index++) {
            $area += ($ring[$index][0] * $ring[$index + 1][1])
                - ($ring[$index + 1][0] * $ring[$index][1]);
        }

        return $area / 2;
    }

    private function boundaryRingHasSelfIntersections(array $ring): bool
    {
        $segmentCount = count($ring) - 1;

        for ($firstIndex = 0; $firstIndex < $segmentCount; $firstIndex++) {
            for ($secondIndex = $firstIndex + 1; $secondIndex < $segmentCount; $secondIndex++) {
                $areAdjacent = abs($firstIndex - $secondIndex) === 1
                    || ($firstIndex === 0 && $secondIndex === $segmentCount - 1);

                if ($areAdjacent) {
                    continue;
                }

                if ($this->boundarySegmentsIntersect(
                    $ring[$firstIndex],
                    $ring[$firstIndex + 1],
                    $ring[$secondIndex],
                    $ring[$secondIndex + 1]
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    private function boundarySegmentsIntersect(array $firstStart, array $firstEnd, array $secondStart, array $secondEnd): bool
    {
        $firstOrientation = $this->boundaryOrientation($firstStart, $firstEnd, $secondStart);
        $secondOrientation = $this->boundaryOrientation($firstStart, $firstEnd, $secondEnd);
        $thirdOrientation = $this->boundaryOrientation($secondStart, $secondEnd, $firstStart);
        $fourthOrientation = $this->boundaryOrientation($secondStart, $secondEnd, $firstEnd);

        if ($firstOrientation !== $secondOrientation && $thirdOrientation !== $fourthOrientation) {
            return true;
        }

        return ($firstOrientation === 0 && $this->boundaryPointOnSegment($secondStart, $firstStart, $firstEnd))
            || ($secondOrientation === 0 && $this->boundaryPointOnSegment($secondEnd, $firstStart, $firstEnd))
            || ($thirdOrientation === 0 && $this->boundaryPointOnSegment($firstStart, $secondStart, $secondEnd))
            || ($fourthOrientation === 0 && $this->boundaryPointOnSegment($firstEnd, $secondStart, $secondEnd));
    }

    private function boundaryOrientation(array $start, array $end, array $point): int
    {
        $value = (($end[1] - $start[1]) * ($point[0] - $end[0]))
            - (($end[0] - $start[0]) * ($point[1] - $end[1]));

        if (abs($value) <= 1.0E-12) {
            return 0;
        }

        return $value > 0 ? 1 : 2;
    }

    private function boundaryPointOnSegment(array $point, array $start, array $end): bool
    {
        $epsilon = 1.0E-12;

        return $point[0] >= min($start[0], $end[0]) - $epsilon
            && $point[0] <= max($start[0], $end[0]) + $epsilon
            && $point[1] >= min($start[1], $end[1]) - $epsilon
            && $point[1] <= max($start[1], $end[1]) + $epsilon;
    }

    public function deleteGpsLocation($id)
    {
        $this->authorizeManage();
        $companyId = auth()->user()->saas_company_id;
        $location = AttendanceGpsLocation::where('saas_company_id', $companyId)->findOrFail($id);
        $location->delete();
        $this->refreshData();
        $this->dispatch('toast', type: 'success', message: tr('GPS location deleted.'));
    }
}
