<?php

declare(strict_types=1);

namespace App\DataObjects;

use App\Enums\HrvStatus;
use Carbon\CarbonImmutable;

/**
 * Normalized wellness for a single day. Garmin's raw JSON shapes are messy and
 * vary; extraction here is best-effort across the likely paths and the full
 * payload is always kept in {@see $raw} as the source of truth, so paths can be
 * refined against real responses without losing data.
 */
final readonly class WellnessSnapshot
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public CarbonImmutable $date,
        public ?int $sleepScore,
        public ?int $sleepDurationS,
        public ?HrvStatus $hrvStatus,
        public ?int $hrvLastNightMs,
        public ?int $hrvBaselineLow,
        public ?int $hrvBaselineHigh,
        public ?int $bodyBatteryHigh,
        public ?int $bodyBatteryLow,
        public ?int $restingHr,
        public ?int $stressAvg,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $w
     */
    public static function fromSidecar(array $w): self
    {
        [$batteryHigh, $batteryLow] = self::bodyBatteryRange($w['body_battery'] ?? null);

        return new self(
            date: CarbonImmutable::parse((string) ($w['date'] ?? 'now')),
            sleepScore: self::asInt(data_get($w, 'sleep.dailySleepDTO.sleepScores.overall.value')),
            sleepDurationS: self::asInt(data_get($w, 'sleep.dailySleepDTO.sleepTimeSeconds')),
            hrvStatus: HrvStatus::fromGarmin(self::asString(data_get($w, 'hrv.hrvSummary.status'))),
            hrvLastNightMs: self::asInt(data_get($w, 'hrv.hrvSummary.lastNightAvg')),
            hrvBaselineLow: self::asInt(data_get($w, 'hrv.hrvSummary.baseline.balancedLow')),
            hrvBaselineHigh: self::asInt(data_get($w, 'hrv.hrvSummary.baseline.balancedUpper')),
            bodyBatteryHigh: $batteryHigh,
            bodyBatteryLow: $batteryLow,
            restingHr: self::asInt(
                data_get($w, 'resting_hr.restingHeartRate')
                ?? data_get($w, 'resting_hr.allMetrics.metricsMap.WELLNESS_RESTING_HEART_RATE.0.value')
            ),
            stressAvg: self::asInt(data_get($w, 'stress.avgStressLevel')),
            raw: $w,
        );
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private static function bodyBatteryRange(mixed $bodyBattery): array
    {
        if (! is_array($bodyBattery)) {
            return [null, null];
        }

        $levels = [];
        foreach ($bodyBattery as $entry) {
            foreach (data_get($entry, 'bodyBatteryValuesArray', []) as $point) {
                $level = is_array($point) ? end($point) : null;
                if (is_numeric($level)) {
                    $levels[] = (int) $level;
                }
            }
        }

        if ($levels === []) {
            return [null, null];
        }

        return [max($levels), min($levels)];
    }

    private static function asInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private static function asString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
