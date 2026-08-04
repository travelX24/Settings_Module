<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\AttendanceGpsLocation;
use Athka\SystemSettings\Support\GeofenceDecision;

class GeofenceService
{
    /**
     * @param array<int, int|array|object> $allowedLocationIds
     */
    public function evaluate(float $lat, float $lng, array $allowedLocationIds): GeofenceDecision
    {
        $ids = collect($allowedLocationIds)
            ->map(fn ($location) => is_object($location)
                ? ($location->id ?? null)
                : (is_array($location) ? ($location['id'] ?? null) : $location))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return GeofenceDecision::deny(
                code: 'no_gps_location_assigned',
                message: $this->message(
                    'لا يوجد موقع حضور جغرافي مرتبط بحسابك.',
                    'No GPS attendance location is assigned to your account.'
                ),
                httpStatus: 422,
            );
        }

        $locations = AttendanceGpsLocation::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get();

        if ($locations->isEmpty()) {
            return GeofenceDecision::deny(
                code: 'no_gps_location_assigned',
                message: $this->message(
                    'لا يوجد موقع حضور جغرافي نشط مرتبط بحسابك.',
                    'No active GPS attendance location is assigned to your account.'
                ),
                httpStatus: 422,
            );
        }

        $nearest = null;
        $nearestDistance = null;

        foreach ($locations as $location) {
            if ($location->isWithinGeofence($lat, $lng)) {
                return GeofenceDecision::allow(
                    message: $this->message(
                        'تم التحقق من موقعك ويمكن تنفيذ عملية الحضور.',
                        'Your location was verified successfully.'
                    ),
                    locationId: (int) $location->id,
                    locationName: (string) $location->name,
                    geofenceType: (string) (
                        $location->geofence_type
                        ?: AttendanceGpsLocation::GEOFENCE_TYPE_CIRCLE
                    ),
                    distanceMeters: 0.0,
                );
            }

            $distance = $location->distanceToBoundaryMeters($lat, $lng);

            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearest = $location;
                $nearestDistance = $distance;
            }
        }

        $locationName = $nearest?->name ? (string) $nearest->name : null;
        $roundedDistance = $nearestDistance !== null
            ? (int) round($nearestDistance)
            : null;

        $message = $locationName && $roundedDistance !== null
            ? $this->message(
                "أنت خارج نطاق موقع {$locationName} بحوالي {$roundedDistance} متر.",
                "You are approximately {$roundedDistance} meters outside {$locationName}."
            )
            : $this->message(
                'أنت خارج نطاق مواقع الحضور المسموحة.',
                'You are outside the allowed attendance locations.'
            );

        return GeofenceDecision::deny(
            code: 'outside_allowed_geofence',
            message: $message,
            httpStatus: 403,
            locationId: $nearest?->id ? (int) $nearest->id : null,
            locationName: $locationName,
            geofenceType: $nearest
                ? (string) (
                    $nearest->geofence_type
                    ?: AttendanceGpsLocation::GEOFENCE_TYPE_CIRCLE
                )
                : null,
            distanceMeters: $nearestDistance,
        );
    }

    /**
     * @param array<int, int|array|object> $allowedLocationIds
     */
    public function isWithinAny(float $lat, float $lng, array $allowedLocationIds): bool
    {
        return $this->evaluate($lat, $lng, $allowedLocationIds)->allowed;
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
