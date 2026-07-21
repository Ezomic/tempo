<?php

declare(strict_types=1);

namespace App\DataObjects;

use App\Enums\Sport;
use Carbon\CarbonImmutable;

final readonly class ActivitySummary
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $externalId,
        public Sport $sport,
        public ?string $subSport,
        public CarbonImmutable $startedAt,
        public ?string $timezone,
        public ?int $durationS,
        public ?int $movingTimeS,
        public ?float $distanceM,
        public ?int $avgHr,
        public ?int $maxHr,
        public ?float $elevationGainM,
        public ?float $avgSpeedMps,
        public ?int $calories,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $a
     */
    public static function fromSidecar(array $a): self
    {
        $typeKey = data_get($a, 'activityType.typeKey');
        $gmt = data_get($a, 'startTimeGMT');

        return new self(
            externalId: (string) ($a['activityId'] ?? ''),
            sport: Sport::fromGarminTypeKey(is_string($typeKey) ? $typeKey : null),
            subSport: is_string($typeKey) ? $typeKey : null,
            startedAt: is_string($gmt) ? CarbonImmutable::parse($gmt, 'UTC') : CarbonImmutable::now(),
            timezone: is_string(data_get($a, 'timeZoneId')) ? (string) data_get($a, 'timeZoneId') : null,
            durationS: self::asInt($a['duration'] ?? null),
            movingTimeS: self::asInt($a['movingDuration'] ?? null),
            distanceM: self::asFloat($a['distance'] ?? null),
            avgHr: self::asInt($a['averageHR'] ?? null),
            maxHr: self::asInt($a['maxHR'] ?? null),
            elevationGainM: self::asFloat($a['elevationGain'] ?? null),
            avgSpeedMps: self::asFloat($a['averageSpeed'] ?? null),
            calories: self::asInt($a['calories'] ?? null),
            raw: $a,
        );
    }

    private static function asInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private static function asFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
