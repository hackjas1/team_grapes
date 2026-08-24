<?php

namespace App\Services;

class GpsValidationService
{
    /**
     * Calculate the great-circle distance between two points in meters
     * using the Haversine formula.
     *
     * @param float $lat1 Latitude of point 1
     * @param float $lon1 Longitude of point 1
     * @param float $lat2 Latitude of point 2
     * @param float $lon2 Longitude of point 2
     * @return float Distance in meters
     */
    public function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusMeters = 6371000.0; // Earth mean radius in meters

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusMeters * $c, 2);
    }

    /**
     * Verify whether student coordinates are within the allowed venue radius.
     *
     * @param float $studentLat
     * @param float $studentLon
     * @param float $venueLat
     * @param float $venueLon
     * @param float $allowedRadiusMeters
     * @return array ['is_valid' => bool, 'distance_meters' => float]
     */
    public function validateRadius(
        float $studentLat,
        float $studentLon,
        float $venueLat,
        float $venueLon,
        float $allowedRadiusMeters
    ): array {
        $distance = $this->calculateDistanceMeters($studentLat, $studentLon, $venueLat, $venueLon);

        return [
            'is_valid' => $distance <= $allowedRadiusMeters,
            'distance_meters' => $distance,
            'allowed_radius_meters' => $allowedRadiusMeters,
        ];
    }

    /**
     * Validate against sudden impossible teleportation jumps (Fake GPS / Mock Location).
     *
     * @param float $currentLat
     * @param float $currentLon
     * @param float|null $prevLat
     * @param float|null $prevLon
     * @param int|null $prevTimestampSeconds
     * @return array ['is_valid' => bool, 'distance_meters' => float, 'speed_kmh' => float, 'error' => string]
     */
    public function validateTeleportation(
        float $currentLat,
        float $currentLon,
        ?float $prevLat,
        ?float $prevLon,
        ?int $prevTimestampSeconds
    ): array {
        if ($prevLat === null || $prevLon === null || $prevTimestampSeconds === null) {
            return ['is_valid' => true];
        }

        $now = time();
        $timeDeltaSeconds = max(1, $now - $prevTimestampSeconds);

        // Only evaluate if the previous location reading was within the last 45 minutes (2700s)
        if ($timeDeltaSeconds > 2700) {
            return ['is_valid' => true];
        }

        $distanceMeters = $this->calculateDistanceMeters($currentLat, $currentLon, $prevLat, $prevLon);

        // Calculate travel speed in km/h
        $speedKmh = ($distanceMeters / 1000.0) / ($timeDeltaSeconds / 3600.0);

        // Detection criteria:
        // 1. Jumped more than 100 meters in under 15 seconds (immediate mock switch)
        // 2. Traveled faster than 60 km/h over short distances (< 30 minutes)
        if (($distanceMeters > 100 && $timeDeltaSeconds <= 15) || ($distanceMeters > 150 && $speedKmh > 60)) {
            $speedFormatted = round($speedKmh, 1);
            $distFormatted = round($distanceMeters);
            return [
                'is_valid' => false,
                'distance_meters' => $distFormatted,
                'time_delta_seconds' => $timeDeltaSeconds,
                'speed_kmh' => $speedFormatted,
                'error' => "Sudden location jump detected ({$distFormatted}m in {$timeDeltaSeconds}s = {$speedFormatted} km/h). Mock Location / Fake GPS is prohibited."
            ];
        }

        return [
            'is_valid' => true,
            'distance_meters' => round($distanceMeters),
            'speed_kmh' => round($speedKmh, 1),
        ];
    }
}
