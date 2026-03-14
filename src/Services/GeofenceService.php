<?php

namespace Athka\SystemSettings\Services;

use Athka\SystemSettings\Models\AttendanceGpsLocation;

class GeofenceService
{
    /**
     * Verify if coordinates are within any of the allowed locations.
     * 
     * @param float $lat
     * @param float $lng
     * @param array $allowedLocationIds List of location IDs to check against
     * @return bool
     */
    public function isWithinAny(float $lat, float $lng, array $allowedLocationIds): bool
    {
        if (empty($allowedLocationIds)) return false;

        foreach ($allowedLocationIds as $locData) {
            $id = is_object($locData) ? $locData->id : (is_array($locData) ? $locData['id'] : $locData);
            
            $locModel = AttendanceGpsLocation::find($id);
            if ($locModel && $locModel->isWithinGeofence($lat, $lng)) {
                return true;
            }
        }

        return false;
    }
}
