<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\DataObjects\GeneratedRoute;
use App\DataObjects\GeoPoint;
use App\Enums\Sport;

interface RouteGenerator
{
    public function isConfigured(): bool;

    /**
     * A fresh home-start loop of roughly `meters`. `seed` drives variety.
     */
    public function loop(GeoPoint $start, int $meters, Sport $sport, int $seed): GeneratedRoute;

    /**
     * An out-and-back on the flattest nearby direction, so repeats land on a
     * flat stretch. Total length is roughly `meters`.
     */
    public function flatOutAndBack(GeoPoint $start, int $meters, Sport $sport): GeneratedRoute;
}
