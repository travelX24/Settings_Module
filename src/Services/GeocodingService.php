<?php

namespace Athka\SystemSettings\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GeocodingService
{
    private const PHOTON_BASE_URL = 'https://photon.komoot.io';

    private const NOMINATIM_BASE_URL = 'https://nominatim.openstreetmap.org';

    private const SEARCH_RESULT_LIMIT = 8;

    private const PROVIDER_RESULT_LIMIT = 20;

    public function reverse(float $lat, float $lng): ?array
    {
        if (! $this->validCoordinates($lat, $lng)) {
            return null;
        }

        $lat = round($lat, 6);
        $lng = round($lng, 6);
        $cacheKey = sprintf('geocoding:reverse:v2:%0.6f:%0.6f', $lat, $lng);

        return Cache::remember(
            $cacheKey,
            now()->addDays(7),
            fn (): ?array => $this->reverseWithoutCache($lat, $lng)
        );
    }

    public function search(
        string $query,
        ?float $lat = null,
        ?float $lng = null,
        ?string $city = null,
        ?string $country = null,
        ?array $bounds = null
    ): array {
        $query = $this->cleanQuery($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $city = $this->cleanContext($city);
        $country = $this->cleanContext($country);
        $bounds = $this->normalizeBounds($bounds);

        $coordinateResult = $this->coordinateResult($query, $lat, $lng);

        if ($coordinateResult) {
            return [$coordinateResult];
        }

        $cacheKey = 'geocoding:search:v4:' . hash('sha256', json_encode([
            mb_strtolower($query),
            $lat !== null ? round($lat, 3) : null,
            $lng !== null ? round($lng, 3) : null,
            mb_strtolower((string) $city),
            mb_strtolower((string) $country),
            $bounds,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return Cache::remember(
            $cacheKey,
            now()->addHours(6),
            fn (): array => $this->searchWithoutCache(
                $query,
                $lat,
                $lng,
                $city,
                $country,
                $bounds
            )
        );
    }

    public function normalizePhotonFeature(
        array $feature,
        int|string $fallbackId = 0
    ): ?array {
        $coordinates = $feature['geometry']['coordinates'] ?? null;
        $properties = $feature['properties'] ?? null;

        if (! is_array($coordinates)
            || count($coordinates) < 2
            || ! is_numeric($coordinates[0])
            || ! is_numeric($coordinates[1])
            || ! is_array($properties)) {
            return null;
        }

        $lng = (float) $coordinates[0];
        $lat = (float) $coordinates[1];

        if (! $this->validCoordinates($lat, $lng)) {
            return null;
        }

        $country = $this->firstValue($properties, ['country']);
        $state = $this->firstValue($properties, ['state']);
        $city = $this->firstValue($properties, [
            'city',
            'town',
        ]);
        $region = $this->firstValue($properties, [
            'district',
            'suburb',
            'quarter',
            'neighbourhood',
            'locality',
            'village',
            'county',
        ]);
        $landmark = $this->firstValue($properties, [
            'name',
            'street',
            'district',
            'suburb',
            'locality',
            'city',
        ]);

        $displayParts = array_values(array_unique(array_filter([
            $landmark,
            $region,
            $city,
            $state,
            $country,
        ], fn ($value): bool => is_string($value) && trim($value) !== '')));

        $osmType = trim((string) ($properties['osm_type'] ?? 'feature'));
        $osmId = trim((string) ($properties['osm_id'] ?? $fallbackId));
        $placeId = sprintf(
            'photon-%s-%s',
            $osmType ?: 'feature',
            $osmId ?: $fallbackId
        );
        $type = $this->firstValue($properties, [
            'osm_value',
            'type',
            'osm_key',
        ]) ?: 'place';
        $category = $this->firstValue($properties, ['osm_key']) ?: $type;
        $extent = $this->normalizePhotonExtent($properties['extent'] ?? null);

        return $this->normalizeReverseResult([
            'place_id' => $placeId,
            'lat' => (string) $lat,
            'lon' => (string) $lng,
            'name' => $landmark ?: ($displayParts[0] ?? ''),
            'display_name' => implode(', ', $displayParts),
            'type' => $type,
            'category' => $category,
            'country_code' => mb_strtolower(trim((string) ($properties['countrycode'] ?? ''))),
            'boundingbox' => $extent,
            'address' => [
                'country' => $country,
                'country_code' => mb_strtolower(
                    trim((string) ($properties['countrycode'] ?? ''))
                ),
                'city' => $city,
                'town' => $this->firstValue($properties, ['town']),
                'village' => $this->firstValue($properties, ['village']),
                'locality' => $this->firstValue($properties, ['locality']),
                'county' => $this->firstValue($properties, ['county']),
                'state' => $state,
                'suburb' => $this->firstValue($properties, ['suburb']),
                'quarter' => $this->firstValue($properties, ['quarter']),
                'neighbourhood' => $this->firstValue(
                    $properties,
                    ['neighbourhood']
                ),
                'district' => $region,
                'road' => $this->firstValue($properties, ['street']),
            ],
            'provider' => 'photon',
        ]);
    }

    public function normalizeNominatimResult(
        array $result,
        int|string $fallbackId = 0
    ): ?array {
        if (! is_numeric($result['lat'] ?? null)
            || ! is_numeric($result['lon'] ?? null)) {
            return null;
        }

        $lat = (float) $result['lat'];
        $lng = (float) $result['lon'];

        if (! $this->validCoordinates($lat, $lng)) {
            return null;
        }

        $address = is_array($result['address'] ?? null)
            ? $result['address']
            : [];
        $namedetails = is_array($result['namedetails'] ?? null)
            ? $result['namedetails']
            : [];
        $displayName = trim((string) ($result['display_name'] ?? ''));
        $name = $this->firstValue($result, ['name'])
            ?: $this->firstValue($namedetails, ['name:ar', 'name', 'name:en'])
            ?: trim((string) explode(',', $displayName)[0]);
        $type = trim((string) ($result['type'] ?? $result['addresstype'] ?? 'place'));
        $category = trim((string) ($result['class'] ?? $type));
        $placeId = trim((string) ($result['place_id'] ?? $fallbackId));

        return [
            'place_id' => 'nominatim-' . ($placeId ?: $fallbackId),
            'lat' => (string) $lat,
            'lon' => (string) $lng,
            'name' => $name,
            'display_name' => $displayName,
            'type' => $type ?: 'place',
            'category' => $category ?: 'place',
            'country_code' => mb_strtolower(trim((string) ($address['country_code'] ?? ''))),
            'boundingbox' => $this->normalizeNominatimBoundingBox(
                $result['boundingbox'] ?? null
            ),
            'address' => $address,
            'provider' => 'nominatim',
        ];
    }

    public function normalizeReverseResult(array $result): array
    {
        $address = is_array($result['address'] ?? null)
            ? $result['address']
            : [];
        $originalLocality = $this->firstValue($address, [
            'locality',
            'village',
            'neighbourhood',
            'suburb',
            'district',
            'quarter',
        ]);
        $majorCity = $this->resolveMajorCity($address);

        if ($majorCity !== '') {
            $address['city'] = $majorCity;
            $address['major_city'] = $majorCity;
        }

        if ($originalLocality !== ''
            && trim((string) ($address['locality'] ?? '')) === '') {
            $address['locality'] = $originalLocality;
        }

        $result['address'] = $address;

        return $result;
    }

    private function reverseWithoutCache(float $lat, float $lng): ?array
    {
        $photon = $this->photonReverse($lat, $lng);

        if ($photon) {
            return $photon;
        }

        return $this->nominatimReverse($lat, $lng);
    }

    private function searchWithoutCache(
        string $query,
        ?float $lat,
        ?float $lng,
        ?string $city,
        ?string $country,
        ?array $bounds
    ): array {
        $queries = $this->buildSearchQueries(
            $query,
            $city,
            $country
        );
        $candidates = [];

        // Search the raw user phrase first. The current map context is only a
        // ranking aid and must never turn "Sana'a" into results from the
        // previously selected city or country.
        foreach ($queries as $searchQuery) {
            $candidates = array_merge(
                $candidates,
                $this->photonSearch(
                    $searchQuery,
                    $lat,
                    $lng
                )
            );
        }

        // Nominatim is a secondary provider for countries, cities, districts,
        // neighbourhoods and roads. Provider failures remain non-blocking.
        foreach ($queries as $searchQuery) {
            if (count($candidates) >= self::PROVIDER_RESULT_LIMIT * 2) {
                break;
            }

            $candidates = array_merge(
                $candidates,
                $this->nominatimSearch(
                    $searchQuery,
                    $lat,
                    $lng,
                    $bounds
                )
            );
        }

        return $this->rankAndLimitResults(
            $candidates,
            $query,
            $lat,
            $lng,
            $city,
            $country,
            $bounds
        );
    }

    private function photonReverse(float $lat, float $lng): ?array
    {
        try {
            $response = $this->photonClient()->get(
                self::PHOTON_BASE_URL . '/reverse',
                [
                    'lat' => $lat,
                    'lon' => $lng,
                ]
            );

            if (! $response->successful()) {
                $this->logProviderFailure(
                    'photon',
                    'reverse',
                    $response->status()
                );

                return null;
            }

            $features = $response->json('features', []);

            if (! is_array($features)
                || ! isset($features[0])
                || ! is_array($features[0])) {
                return null;
            }

            return $this->normalizePhotonFeature($features[0]);
        } catch (Throwable $error) {
            $this->logProviderException('photon', 'reverse', $error);

            return null;
        }
    }

    private function photonSearch(
        string $query,
        ?float $lat,
        ?float $lng
    ): array {
        $params = [
            'q' => $query,
            'limit' => self::PROVIDER_RESULT_LIMIT,
        ];

        if ($lat !== null
            && $lng !== null
            && $this->validCoordinates($lat, $lng)) {
            $params['lat'] = $lat;
            $params['lon'] = $lng;
        }

        try {
            $response = $this->photonClient()->get(
                self::PHOTON_BASE_URL . '/api',
                $params
            );

            if (! $response->successful()) {
                $this->logProviderFailure(
                    'photon',
                    'search',
                    $response->status()
                );

                return [];
            }

            $features = $response->json('features', []);

            if (! is_array($features)) {
                return [];
            }

            return array_values(array_filter(array_map(
                fn ($feature, $index): ?array => is_array($feature)
                    ? $this->normalizePhotonFeature($feature, $index)
                    : null,
                $features,
                array_keys($features)
            )));
        } catch (Throwable $error) {
            $this->logProviderException('photon', 'search', $error);

            return [];
        }
    }

    private function nominatimReverse(float $lat, float $lng): ?array
    {
        try {
            $response = $this->nominatimClient()->get(
                self::NOMINATIM_BASE_URL . '/reverse',
                [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 18,
                    'addressdetails' => 1,
                    'namedetails' => 1,
                ]
            );

            if (! $response->successful()) {
                $this->logProviderFailure(
                    'nominatim',
                    'reverse',
                    $response->status()
                );

                return null;
            }

            $result = $response->json();

            if (! is_array($result)
                || ! is_array($result['address'] ?? null)) {
                return null;
            }

            $result['provider'] = 'nominatim';

            return $this->normalizeReverseResult($result);
        } catch (Throwable $error) {
            $this->logProviderException('nominatim', 'reverse', $error);

            return null;
        }
    }

    private function nominatimSearch(
        string $query,
        ?float $lat,
        ?float $lng,
        ?array $bounds
    ): array {
        $params = [
            'format' => 'jsonv2',
            'q' => $query,
            'limit' => self::PROVIDER_RESULT_LIMIT,
            'addressdetails' => 1,
            'namedetails' => 1,
            'dedupe' => 1,
        ];

        if ($bounds) {
            $params['viewbox'] = implode(',', [
                $bounds['west'],
                $bounds['north'],
                $bounds['east'],
                $bounds['south'],
            ]);
            $params['bounded'] = 0;
        } elseif ($lat !== null
            && $lng !== null
            && $this->validCoordinates($lat, $lng)) {
            $delta = 0.35;
            $params['viewbox'] = implode(',', [
                $lng - $delta,
                $lat + $delta,
                $lng + $delta,
                $lat - $delta,
            ]);
            $params['bounded'] = 0;
        }

        try {
            $response = $this->nominatimClient()->get(
                self::NOMINATIM_BASE_URL . '/search',
                $params
            );

            if (! $response->successful()) {
                $this->logProviderFailure(
                    'nominatim',
                    'search',
                    $response->status()
                );

                return [];
            }

            $results = $response->json();

            if (! is_array($results)) {
                return [];
            }

            return array_values(array_filter(array_map(
                fn ($result, $index): ?array => is_array($result)
                    ? $this->normalizeNominatimResult($result, $index)
                    : null,
                $results,
                array_keys($results)
            )));
        } catch (Throwable $error) {
            $this->logProviderException('nominatim', 'search', $error);

            return [];
        }
    }

    private function buildSearchQueries(
        string $query,
        ?string $city,
        ?string $country
    ): array {
        $countryQuery = implode(', ', array_filter([
            $query,
            $country,
        ]));
        $cityCountryQuery = implode(', ', array_filter([
            $query,
            $city,
            $country,
        ]));

        return array_values(array_unique(array_filter([
            $query,
            $countryQuery,
            $cityCountryQuery,
        ])));
    }

    private function rankAndLimitResults(
        array $candidates,
        string $query,
        ?float $lat,
        ?float $lng,
        ?string $city,
        ?string $country,
        ?array $bounds
    ): array {
        $ranked = [];
        $seen = [];
        $normalizedQuery = $this->normalizeComparableText($query);
        $normalizedCity = $this->normalizeComparableText((string) $city);
        $normalizedCountry = $this->normalizeComparableText((string) $country);
        $hasCenter = $lat !== null
            && $lng !== null
            && $this->validCoordinates($lat, $lng);

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)
                || ! is_numeric($candidate['lat'] ?? null)
                || ! is_numeric($candidate['lon'] ?? null)) {
                continue;
            }

            $candidateLat = (float) $candidate['lat'];
            $candidateLng = (float) $candidate['lon'];

            if (! $this->validCoordinates($candidateLat, $candidateLng)) {
                continue;
            }

            $displayName = trim((string) ($candidate['display_name'] ?? ''));
            $name = trim((string) ($candidate['name'] ?? ''))
                ?: trim((string) explode(',', $displayName)[0]);
            $dedupeKey = sprintf(
                '%.5f:%.5f:%s',
                $candidateLat,
                $candidateLng,
                $this->normalizeComparableText($name)
            );

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $score = 0.0;
            $normalizedName = $this->normalizeComparableText($name);
            $normalizedDisplay = $this->normalizeComparableText($displayName);
            $candidateCity = $this->normalizeComparableText(
                $this->resultContextValue($candidate, [
                    'city',
                    'town',
                    'village',
                    'state',
                ])
            );
            $candidateCountry = $this->normalizeComparableText(
                $this->resultContextValue($candidate, ['country'])
            );

            if (! $this->isSupportedLocationResult($candidate)) {
                continue;
            }

            $textMatchScore = $this->textMatchScore(
                $normalizedQuery,
                $normalizedName,
                $normalizedDisplay,
                $candidate
            );

            // Never display unrelated provider suggestions. This prevents a
            // query such as "Sana'a" from showing stale or remote Oman rows.
            if ($textMatchScore <= 0) {
                continue;
            }

            $score += $textMatchScore;

            if ($normalizedCity !== ''
                && $candidateCity !== ''
                && (str_contains($candidateCity, $normalizedCity)
                    || str_contains($normalizedCity, $candidateCity))) {
                $score += 55;
            }

            if ($normalizedCountry !== ''
                && $candidateCountry !== ''
                && (str_contains($candidateCountry, $normalizedCountry)
                    || str_contains($normalizedCountry, $candidateCountry))) {
                $score += 45;
            }

            $insideBounds = $bounds
                ? $this->pointInsideBounds($candidateLat, $candidateLng, $bounds)
                : false;

            if ($insideBounds) {
                $score += 70;
            }

            $distanceKm = null;

            if ($hasCenter) {
                $distanceKm = $this->distanceKm(
                    (float) $lat,
                    (float) $lng,
                    $candidateLat,
                    $candidateLng
                );
                $score += $this->distanceScore($distanceKm);
            }

            $score += $this->typeScore(
                (string) ($candidate['type'] ?? ''),
                (string) ($candidate['category'] ?? '')
            );

            $candidate['name'] = $name;
            $candidate['display_name'] = $displayName ?: $name;
            $candidate['distance_km'] = $distanceKm !== null
                ? round($distanceKm, 1)
                : null;
            $candidate['is_nearby'] = $insideBounds
                || ($distanceKm !== null && $distanceKm <= 50);
            $candidate['_score'] = round($score, 3);
            $ranked[] = $candidate;
        }

        usort($ranked, function (array $left, array $right): int {
            $scoreCompare = ($right['_score'] ?? 0) <=> ($left['_score'] ?? 0);

            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            $leftDistance = $left['distance_km'] ?? PHP_FLOAT_MAX;
            $rightDistance = $right['distance_km'] ?? PHP_FLOAT_MAX;
            $distanceCompare = $leftDistance <=> $rightDistance;

            if ($distanceCompare !== 0) {
                return $distanceCompare;
            }

            return strcmp(
                (string) ($left['display_name'] ?? ''),
                (string) ($right['display_name'] ?? '')
            );
        });

        $ranked = array_slice($ranked, 0, self::SEARCH_RESULT_LIMIT);

        return array_values(array_map(function (array $result): array {
            unset($result['_score']);

            return $result;
        }, $ranked));
    }

    private function coordinateResult(
        string $query,
        ?float $centerLat,
        ?float $centerLng
    ): ?array {
        $normalized = str_replace(['،', ';'], ',', $query);
        $matches = [];
        $pattern = '/^\s*([+-]?(?:\d+(?:\.\d+)?|\.\d+))\s*(?:,|\s+)\s*([+-]?(?:\d+(?:\.\d+)?|\.\d+))\s*$/u';

        if (preg_match($pattern, $normalized, $matches) !== 1) {
            return null;
        }

        $lat = (float) $matches[1];
        $lng = (float) $matches[2];

        if (! $this->validCoordinates($lat, $lng)) {
            return null;
        }

        $distanceKm = null;

        if ($centerLat !== null
            && $centerLng !== null
            && $this->validCoordinates($centerLat, $centerLng)) {
            $distanceKm = round(
                $this->distanceKm($centerLat, $centerLng, $lat, $lng),
                1
            );
        }

        $display = sprintf('%.6f, %.6f', $lat, $lng);

        return [
            'place_id' => 'coordinates-' . md5($display),
            'lat' => (string) $lat,
            'lon' => (string) $lng,
            'name' => $display,
            'display_name' => $display,
            'type' => 'coordinates',
            'category' => 'coordinates',
            'country_code' => '',
            'boundingbox' => null,
            'address' => [],
            'provider' => 'coordinates',
            'distance_km' => $distanceKm,
            'is_nearby' => $distanceKm !== null && $distanceKm <= 50,
        ];
    }

    private function isSupportedLocationResult(array $candidate): bool
    {
        $address = is_array($candidate['address'] ?? null)
            ? $candidate['address']
            : [];
        $value = $this->normalizeComparableText(implode(' ', array_filter([
            (string) ($candidate['type'] ?? ''),
            (string) ($candidate['category'] ?? ''),
            (string) ($address['addresstype'] ?? ''),
        ])));
        $supported = [
            'country',
            'state',
            'province',
            'region',
            'governorate',
            'administrative',
            'boundary',
            'county',
            'municipality',
            'city',
            'town',
            'village',
            'hamlet',
            'district',
            'suburb',
            'neighbourhood',
            'neighborhood',
            'quarter',
            'residential',
            'locality',
            'road',
            'street',
            'highway',
            'place',
        ];

        foreach ($supported as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function textMatchScore(
        string $normalizedQuery,
        string $normalizedName,
        string $normalizedDisplay,
        array $candidate
    ): float {
        if ($normalizedQuery === '') {
            return 0;
        }

        if ($normalizedName === $normalizedQuery) {
            return 240;
        }

        if ($normalizedName !== ''
            && str_starts_with($normalizedName, $normalizedQuery)) {
            return 190;
        }

        if ($normalizedName !== ''
            && str_contains($normalizedName, $normalizedQuery)) {
            return 155;
        }

        if ($normalizedDisplay !== ''
            && str_contains($normalizedDisplay, $normalizedQuery)) {
            return 125;
        }

        $address = is_array($candidate['address'] ?? null)
            ? $candidate['address']
            : [];
        $searchable = $this->normalizeComparableText(implode(' ', array_filter([
            $normalizedName,
            $normalizedDisplay,
            ...array_values($address),
        ], fn ($value): bool => is_scalar($value))));
        $tokens = array_values(array_filter(
            explode(' ', $normalizedQuery),
            fn (string $token): bool => mb_strlen($token) >= 2
        ));

        if ($tokens === []) {
            return 0;
        }

        $matched = 0;

        foreach ($tokens as $token) {
            if (str_contains($searchable, $token)) {
                $matched++;
            }
        }

        $coverage = $matched / count($tokens);

        return match (true) {
            $coverage >= 1 => 100,
            $coverage >= 0.67 => 65,
            default => 0,
        };
    }

    private function distanceScore(float $distanceKm): float
    {
        return match (true) {
            $distanceKm <= 1 => 95,
            $distanceKm <= 5 => 80,
            $distanceKm <= 20 => 60,
            $distanceKm <= 100 => 38,
            $distanceKm <= 500 => 15,
            $distanceKm <= 1000 => 0,
            default => -35,
        };
    }

    private function typeScore(string $type, string $category): float
    {
        $value = $this->normalizeComparableText($type . ' ' . $category);
        $weights = [
            'country' => 55,
            'state' => 52,
            'province' => 52,
            'region' => 50,
            'governorate' => 50,
            'administrative' => 48,
            'boundary' => 46,
            'city' => 50,
            'town' => 48,
            'municipality' => 46,
            'village' => 44,
            'hamlet' => 42,
            'district' => 44,
            'suburb' => 42,
            'neighbourhood' => 42,
            'neighborhood' => 42,
            'quarter' => 40,
            'residential' => 38,
            'locality' => 38,
            'road' => 40,
            'street' => 40,
            'highway' => 38,
            'place' => 28,
        ];

        foreach ($weights as $needle => $weight) {
            if (str_contains($value, $needle)) {
                return $weight;
            }
        }

        return 6;
    }

    private function distanceKm(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng
    ): float {
        $earthRadiusKm = 6371.0088;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $fromLatRadians = deg2rad($fromLat);
        $toLatRadians = deg2rad($toLat);
        $a = sin($latDelta / 2) ** 2
            + cos($fromLatRadians)
            * cos($toLatRadians)
            * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function pointInsideBounds(
        float $lat,
        float $lng,
        array $bounds
    ): bool {
        return $lat >= $bounds['south']
            && $lat <= $bounds['north']
            && $lng >= $bounds['west']
            && $lng <= $bounds['east'];
    }

    private function normalizeBounds(?array $bounds): ?array
    {
        if (! is_array($bounds)) {
            return null;
        }

        $south = $bounds['south'] ?? $bounds[0] ?? null;
        $west = $bounds['west'] ?? $bounds[1] ?? null;
        $north = $bounds['north'] ?? $bounds[2] ?? null;
        $east = $bounds['east'] ?? $bounds[3] ?? null;

        if (! is_numeric($south)
            || ! is_numeric($west)
            || ! is_numeric($north)
            || ! is_numeric($east)) {
            return null;
        }

        $south = (float) $south;
        $west = (float) $west;
        $north = (float) $north;
        $east = (float) $east;

        if (! $this->validCoordinates($south, $west)
            || ! $this->validCoordinates($north, $east)
            || $south > $north
            || $west > $east) {
            return null;
        }

        return compact('south', 'west', 'north', 'east');
    }

    private function normalizePhotonExtent(mixed $extent): ?array
    {
        if (! is_array($extent)
            || count($extent) < 4
            || ! is_numeric($extent[0])
            || ! is_numeric($extent[1])
            || ! is_numeric($extent[2])
            || ! is_numeric($extent[3])) {
            return null;
        }

        return [
            (string) (float) $extent[1],
            (string) (float) $extent[3],
            (string) (float) $extent[0],
            (string) (float) $extent[2],
        ];
    }

    private function normalizeNominatimBoundingBox(mixed $boundingBox): ?array
    {
        if (! is_array($boundingBox)
            || count($boundingBox) < 4
            || ! is_numeric($boundingBox[0])
            || ! is_numeric($boundingBox[1])
            || ! is_numeric($boundingBox[2])
            || ! is_numeric($boundingBox[3])) {
            return null;
        }

        return array_map(
            fn ($value): string => (string) (float) $value,
            array_slice($boundingBox, 0, 4)
        );
    }

    private function resultContextValue(array $result, array $keys): string
    {
        $address = is_array($result['address'] ?? null)
            ? $result['address']
            : [];

        return $this->firstValue($address, $keys);
    }


    private function resolveMajorCity(array $address): string
    {
        $countryCode = mb_strtolower(trim((string) (
            $address['country_code'] ?? ''
        )));
        $country = $this->normalizeComparableText(
            (string) ($address['country'] ?? '')
        );
        $isYemen = $countryCode === 'ye'
            || str_contains($country, 'اليمن')
            || str_contains($country, 'yemen');

        $directCity = $this->firstValue($address, [
            'city',
            'town',
        ]);

        if ($directCity !== '' && ! $this->looksLikeLocalArea($directCity)) {
            if ($isYemen) {
                return $this->canonicalYemenCity($directCity)
                    ?: $directCity;
            }

            return $directCity;
        }

        $administrativeArea = $this->firstValue($address, [
            'state',
            'province',
            'region',
            'governorate',
            'state_district',
            'county',
            'municipality',
        ]);

        if ($administrativeArea !== '') {
            if ($isYemen) {
                $canonical = $this->canonicalYemenCity(
                    $administrativeArea
                );

                if ($canonical !== '') {
                    return $canonical;
                }
            }

            return $this->cleanAdministrativeCityName(
                $administrativeArea
            );
        }

        return $this->firstValue($address, ['village']);
    }

    private function canonicalYemenCity(string $value): string
    {
        $normalized = $this->normalizeComparableText($value);
        $normalized = preg_replace(
            '/^(مدينه|محافظه|امانه)\s+/u',
            '',
            $normalized
        );
        $normalized = preg_replace(
            '/\s+(governorate|municipality|province|region)$/u',
            '',
            (string) $normalized
        );
        $normalized = trim((string) $normalized);

        $aliases = [
            'امانه العاصمه' => 'صنعاء',
            'amanat al asimah' => 'صنعاء',
            'sana a' => 'صنعاء',
            'sanaa' => 'صنعاء',
            'san a' => 'صنعاء',

            'اب' => 'إب',
            'ibb' => 'إب',

            'تعز' => 'تعز',
            'taiz' => 'تعز',
            'taizz' => 'تعز',

            'عدن' => 'عدن',
            'aden' => 'عدن',

            'ذمار' => 'ذمار',
            'dhamar' => 'ذمار',

            'الحديده' => 'الحديدة',
            'hodeidah' => 'الحديدة',
            'al hudaydah' => 'الحديدة',
            'hudaydah' => 'الحديدة',

            'مارب' => 'مأرب',
            'marib' => 'مأرب',

            'عمران' => 'عمران',
            'amran' => 'عمران',

            'صعده' => 'صعدة',
            'saada' => 'صعدة',
            'sadah' => 'صعدة',

            'حجه' => 'حجة',
            'hajjah' => 'حجة',

            'المحويت' => 'المحويت',
            'al mahwit' => 'المحويت',
            'mahwit' => 'المحويت',

            'البيضاء' => 'البيضاء',
            'al bayda' => 'البيضاء',
            'al bayda' => 'البيضاء',

            'الضالع' => 'الضالع',
            'al dhale e' => 'الضالع',
            'al dhale' => 'الضالع',

            'شبوه' => 'شبوة',
            'shabwah' => 'شبوة',
            'shabwa' => 'شبوة',

            'المهره' => 'المهرة',
            'al mahrah' => 'المهرة',
            'mahrah' => 'المهرة',

            'لحج' => 'لحج',
            'lahij' => 'لحج',
            'lahj' => 'لحج',

            'ريمه' => 'ريمة',
            'raymah' => 'ريمة',
            'remah' => 'ريمة',

            'الجوف' => 'الجوف',
            'al jawf' => 'الجوف',
            'jawf' => 'الجوف',

            'حضرموت' => 'حضرموت',
            'hadramaut' => 'حضرموت',
            'hadhramaut' => 'حضرموت',

            'ابين' => 'أبين',
            'abyan' => 'أبين',

            'سقطري' => 'سقطرى',
            'سقطرى' => 'سقطرى',
            'socotra' => 'سقطرى',
        ];

        return $aliases[$normalized] ?? '';
    }

    private function looksLikeLocalArea(string $value): bool
    {
        $normalized = $this->normalizeComparableText($value);
        $prefixes = [
            'حاره ',
            'حي ',
            'مديريه ',
            'منطقه ',
            'عزله ',
            'قريه ',
            'محله ',
            'شارع ',
            'neighbourhood ',
            'neighborhood ',
            'suburb ',
            'district ',
            'quarter ',
            'locality ',
            'village ',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function cleanAdministrativeCityName(string $value): string
    {
        $cleaned = trim($value);
        $cleaned = preg_replace(
            '/^(محافظة|أمانة|امانة|مدينة)\s+/u',
            '',
            $cleaned
        );
        $cleaned = preg_replace(
            '/\s+(Governorate|Municipality|Province|Region)$/iu',
            '',
            (string) $cleaned
        );

        return trim((string) $cleaned);
    }

    private function cleanQuery(string $query): string
    {
        $query = preg_replace('/\s+/u', ' ', trim($query));

        return is_string($query) ? $query : '';
    }

    private function normalizeComparableText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value);
        $value = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', (string) $value);
        $value = str_replace(['ى'], 'ي', $value);
        $value = str_replace(['ة'], 'ه', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $value);
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return is_string($value) ? $value : '';
    }

    private function photonClient()
    {
        return Http::acceptJson()
            ->withHeaders([
                'User-Agent' => 'AthkaHR/1.0 (system-settings geocoder)',
                'Accept-Language' => 'ar,en;q=0.8',
            ])
            ->connectTimeout(5)
            ->timeout(10);
    }

    private function nominatimClient()
    {
        return Http::acceptJson()
            ->withHeaders([
                'User-Agent' => 'AthkaHR/1.0 (system-settings geocoder)',
                'Accept-Language' => 'ar,en;q=0.8',
            ])
            ->connectTimeout(2)
            ->timeout(4);
    }

    private function validCoordinates(float $lat, float $lng): bool
    {
        return $lat >= -90
            && $lat <= 90
            && $lng >= -180
            && $lng <= 180;
    }

    private function cleanContext(?string $value): ?string
    {
        $value = trim((string) $value);

        return in_array($value, ['', '...', '---'], true)
            ? null
            : $value;
    }

    private function firstValue(array $values, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($values[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function logProviderFailure(
        string $provider,
        string $operation,
        int $status
    ): void {
        Log::warning(
            'Geocoding provider returned an unsuccessful response.',
            [
                'provider' => $provider,
                'operation' => $operation,
                'status' => $status,
            ]
        );
    }

    private function logProviderException(
        string $provider,
        string $operation,
        Throwable $error
    ): void {
        Log::warning(
            'Geocoding provider request failed.',
            [
                'provider' => $provider,
                'operation' => $operation,
                'message' => $error->getMessage(),
            ]
        );
    }
}
