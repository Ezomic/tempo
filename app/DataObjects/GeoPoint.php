<?php

declare(strict_types=1);

namespace App\DataObjects;

final readonly class GeoPoint
{
    public function __construct(
        public float $lat,
        public float $lng,
    ) {}

    /**
     * Destination point `meters` away along `bearing` degrees, via the
     * spherical earth forward formula. Used to seed out-and-back candidates.
     */
    public function destination(float $bearing, int $meters): self
    {
        $earth = 6371000.0;
        $angular = $meters / $earth;
        $bearingRad = deg2rad($bearing);
        $latRad = deg2rad($this->lat);
        $lngRad = deg2rad($this->lng);

        $destLat = asin(
            sin($latRad) * cos($angular) +
            cos($latRad) * sin($angular) * cos($bearingRad)
        );

        $destLng = $lngRad + atan2(
            sin($bearingRad) * sin($angular) * cos($latRad),
            cos($angular) - sin($latRad) * sin($destLat)
        );

        return new self(rad2deg($destLat), rad2deg($destLng));
    }

    /**
     * @return array{0: float, 1: float} [lng, lat] as ORS expects
     */
    public function toOrs(): array
    {
        return [$this->lng, $this->lat];
    }
}
