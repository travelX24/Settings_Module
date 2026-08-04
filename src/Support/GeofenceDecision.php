<?php

namespace Athka\SystemSettings\Support;

final class GeofenceDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $code,
        public readonly string $message,
        public readonly int $httpStatus = 200,
        public readonly ?int $locationId = null,
        public readonly ?string $locationName = null,
        public readonly ?string $geofenceType = null,
        public readonly ?float $distanceMeters = null,
        public readonly ?float $gpsAccuracy = null,
        public readonly ?string $locationCapturedAt = null,
    ) {
    }

    public static function allow(
        string $message,
        ?int $locationId = null,
        ?string $locationName = null,
        ?string $geofenceType = null,
        ?float $distanceMeters = 0.0,
    ): self {
        return new self(
            allowed: true,
            code: 'inside_allowed_geofence',
            message: $message,
            httpStatus: 200,
            locationId: $locationId,
            locationName: $locationName,
            geofenceType: $geofenceType,
            distanceMeters: $distanceMeters,
        );
    }

    public static function deny(
        string $code,
        string $message,
        int $httpStatus = 422,
        ?int $locationId = null,
        ?string $locationName = null,
        ?string $geofenceType = null,
        ?float $distanceMeters = null,
    ): self {
        return new self(
            allowed: false,
            code: $code,
            message: $message,
            httpStatus: $httpStatus,
            locationId: $locationId,
            locationName: $locationName,
            geofenceType: $geofenceType,
            distanceMeters: $distanceMeters,
        );
    }

    public function withCaptureMeta(?float $gpsAccuracy, ?string $locationCapturedAt): self
    {
        return new self(
            allowed: $this->allowed,
            code: $this->code,
            message: $this->message,
            httpStatus: $this->httpStatus,
            locationId: $this->locationId,
            locationName: $this->locationName,
            geofenceType: $this->geofenceType,
            distanceMeters: $this->distanceMeters,
            gpsAccuracy: $gpsAccuracy,
            locationCapturedAt: $locationCapturedAt,
        );
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'code' => $this->code,
            'message' => $this->message,
            'location_id' => $this->locationId,
            'location_name' => $this->locationName,
            'geofence_type' => $this->geofenceType,
            'distance_meters' => $this->distanceMeters !== null
                ? round($this->distanceMeters, 1)
                : null,
            'gps_accuracy' => $this->gpsAccuracy,
            'location_captured_at' => $this->locationCapturedAt,
        ];
    }

    public function toResponseArray(): array
    {
        return [
            'ok' => false,
            'code' => $this->code,
            'legacy_code' => in_array(
                $this->code,
                ['outside_allowed_geofence', 'no_gps_location_assigned'],
                true
            ) ? 'geofence_error' : null,
            'message' => $this->message,
            'location_gate' => $this->toArray(),
        ];
    }
}
