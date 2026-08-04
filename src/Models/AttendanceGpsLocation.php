<?php

namespace Athka\SystemSettings\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class AttendanceGpsLocation extends Model
{
    use HasFactory;

    public const GEOFENCE_TYPE_CIRCLE = 'circle';

    public const GEOFENCE_TYPE_POLYGON = 'polygon';

    protected $fillable = [
        'name',
        'address_text',
        'country',
        'city',
        'region',
        'lat',
        'lng',
        'radius_meters',
        'geofence_type',
        'boundary_geojson',
        'branch_id',
        'employee_group_id',
        'is_active',
        'saas_company_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'radius_meters' => 'integer',
        'boundary_geojson' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        $forgetCompanyCache = static function (AttendanceGpsLocation $location): void {
            if ($location->saas_company_id) {
                Cache::forget("attendance:config:gps-locations:{$location->saas_company_id}");
            }
        };

        static::saved($forgetCompanyCache);
        static::deleted($forgetCompanyCache);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\Athka\Saas\Models\Branch::class, 'branch_id');
    }

    public function employeeGroup(): BelongsTo
    {
        return $this->belongsTo(EmployeeGroup::class, 'employee_group_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isWithinGeofence(float $lat, float $lng): bool
    {
        if ($this->geofence_type === self::GEOFENCE_TYPE_POLYGON) {
            return $this->isWithinPolygonBoundary($lat, $lng);
        }

        return $this->isWithinCircleBoundary($lat, $lng);
    }

    public function isWithinCircleBoundary(float $lat, float $lng): bool
    {
        return $this->distanceFromPointMeters($lat, $lng)
            <= (int) $this->radius_meters;
    }

    public function distanceFromPointMeters(float $lat, float $lng): float
    {
        $earthRadius = 6371000;

        $latFrom = deg2rad((float) $this->lat);
        $lngFrom = deg2rad((float) $this->lng);
        $latTo = deg2rad($lat);
        $lngTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(
            sqrt($a),
            sqrt(max(0, 1 - $a))
        );
    }

    public function distanceToBoundaryMeters(float $lat, float $lng): float
    {
        if ($this->isWithinGeofence($lat, $lng)) {
            return 0.0;
        }

        if ($this->geofence_type !== self::GEOFENCE_TYPE_POLYGON) {
            return max(
                0.0,
                $this->distanceFromPointMeters($lat, $lng)
                    - (int) $this->radius_meters
            );
        }

        $geometry = $this->normalizeBoundaryGeometry(
            $this->boundary_geojson
        );

        if (! $geometry) {
            return $this->distanceFromPointMeters($lat, $lng);
        }

        $rings = $this->boundaryRings($geometry);
        $nearest = null;

        foreach ($rings as $ring) {
            $points = array_values(array_filter(
                $ring,
                fn ($point) => is_array($point)
                    && count($point) >= 2
                    && is_numeric($point[0])
                    && is_numeric($point[1])
            ));

            for ($index = 1; $index < count($points); $index++) {
                $distance = $this->distancePointToSegmentMeters(
                    pointLat: $lat,
                    pointLng: $lng,
                    startLat: (float) $points[$index - 1][1],
                    startLng: (float) $points[$index - 1][0],
                    endLat: (float) $points[$index][1],
                    endLng: (float) $points[$index][0],
                );

                if ($nearest === null || $distance < $nearest) {
                    $nearest = $distance;
                }
            }
        }

        return $nearest ?? $this->distanceFromPointMeters($lat, $lng);
    }

    public function isWithinPolygonBoundary(float $lat, float $lng): bool
    {
        $geometry = $this->normalizeBoundaryGeometry($this->boundary_geojson);

        if (! $geometry) {
            return false;
        }

        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            return false;
        }

        return match ($geometry['type'] ?? null) {
            'Polygon' => $this->polygonContainsPoint($coordinates, $lat, $lng),
            'MultiPolygon' => collect($coordinates)
                ->contains(fn ($polygon) => is_array($polygon)
                    && $this->polygonContainsPoint($polygon, $lat, $lng)),
            default => false,
        };
    }

    private function normalizeBoundaryGeometry(mixed $boundary): ?array
    {
        if (is_string($boundary)) {
            $boundary = json_decode($boundary, true);
        }

        if (! is_array($boundary)) {
            return null;
        }

        if (($boundary['type'] ?? null) === 'Feature') {
            $boundary = $boundary['geometry'] ?? null;
        }

        if (! is_array($boundary)) {
            return null;
        }

        if (! in_array($boundary['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            return null;
        }

        return $boundary;
    }

    private function polygonContainsPoint(array $rings, float $lat, float $lng): bool
    {
        $outerRing = $rings[0] ?? null;

        if (! is_array($outerRing) || ! $this->ringContainsPoint($outerRing, $lat, $lng)) {
            return false;
        }

        foreach (array_slice($rings, 1) as $hole) {
            if (is_array($hole) && $this->ringContainsPoint($hole, $lat, $lng)) {
                return false;
            }
        }

        return true;
    }

    private function ringContainsPoint(array $ring, float $lat, float $lng): bool
    {
        $points = array_values(array_filter(
            $ring,
            fn ($point) => is_array($point)
                && count($point) >= 2
                && is_numeric($point[0])
                && is_numeric($point[1])
        ));

        if (count($points) < 4) {
            return false;
        }

        $first = $points[0];
        $last = $points[array_key_last($points)];

        if (abs((float) $first[0] - (float) $last[0]) > 1.0E-10
            || abs((float) $first[1] - (float) $last[1]) > 1.0E-10) {
            return false;
        }

        $uniquePoints = array_unique(array_map(
            fn ($point) => sprintf('%.12F,%.12F', (float) $point[0], (float) $point[1]),
            array_slice($points, 0, -1)
        ));

        if (count($uniquePoints) < 3) {
            return false;
        }

        $inside = false;
        $count = count($points);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $x1 = (float) $points[$j][0];
            $y1 = (float) $points[$j][1];
            $x2 = (float) $points[$i][0];
            $y2 = (float) $points[$i][1];

            if ($this->pointOnSegment($lng, $lat, $x1, $y1, $x2, $y2)) {
                return true;
            }

            $crossesLatitude = ($y1 > $lat) !== ($y2 > $lat);

            if (! $crossesLatitude) {
                continue;
            }

            $intersectionLng = (($x2 - $x1) * ($lat - $y1) / ($y2 - $y1)) + $x1;

            if ($lng < $intersectionLng) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function pointOnSegment(
        float $pointX,
        float $pointY,
        float $startX,
        float $startY,
        float $endX,
        float $endY
    ): bool {
        $epsilon = 1.0E-10;
        $crossProduct = ($pointY - $startY) * ($endX - $startX)
            - ($pointX - $startX) * ($endY - $startY);

        if (abs($crossProduct) > $epsilon) {
            return false;
        }

        return $pointX >= min($startX, $endX) - $epsilon
            && $pointX <= max($startX, $endX) + $epsilon
            && $pointY >= min($startY, $endY) - $epsilon
            && $pointY <= max($startY, $endY) + $epsilon;
    }

    private function boundaryRings(array $geometry): array
    {
        $coordinates = $geometry['coordinates'] ?? [];

        if (($geometry['type'] ?? null) === 'Polygon') {
            return is_array($coordinates) ? $coordinates : [];
        }

        if (($geometry['type'] ?? null) !== 'MultiPolygon'
            || ! is_array($coordinates)) {
            return [];
        }

        $rings = [];

        foreach ($coordinates as $polygon) {
            if (! is_array($polygon)) {
                continue;
            }

            foreach ($polygon as $ring) {
                if (is_array($ring)) {
                    $rings[] = $ring;
                }
            }
        }

        return $rings;
    }

    private function distancePointToSegmentMeters(
        float $pointLat,
        float $pointLng,
        float $startLat,
        float $startLng,
        float $endLat,
        float $endLng,
    ): float {
        $meanLatitudeRadians = deg2rad(
            ($pointLat + $startLat + $endLat) / 3
        );

        $metersPerLongitudeDegree = 111320
            * max(0.01, cos($meanLatitudeRadians));
        $metersPerLatitudeDegree = 110540;

        $pointX = ($pointLng - $startLng) * $metersPerLongitudeDegree;
        $pointY = ($pointLat - $startLat) * $metersPerLatitudeDegree;
        $segmentX = ($endLng - $startLng) * $metersPerLongitudeDegree;
        $segmentY = ($endLat - $startLat) * $metersPerLatitudeDegree;

        $segmentLengthSquared = ($segmentX ** 2) + ($segmentY ** 2);

        if ($segmentLengthSquared <= 0.0) {
            return sqrt(($pointX ** 2) + ($pointY ** 2));
        }

        $projection = (($pointX * $segmentX) + ($pointY * $segmentY))
            / $segmentLengthSquared;
        $projection = min(1.0, max(0.0, $projection));

        $nearestX = $projection * $segmentX;
        $nearestY = $projection * $segmentY;

        return sqrt(
            (($pointX - $nearestX) ** 2)
            + (($pointY - $nearestY) ** 2)
        );
    }
}
