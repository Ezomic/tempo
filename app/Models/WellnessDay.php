<?php

namespace App\Models;

use App\Enums\HrvStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property int|null $sleep_score
 * @property int|null $sleep_duration_s
 * @property HrvStatus|null $hrv_status
 * @property int|null $hrv_last_night_ms
 * @property int|null $hrv_baseline_low
 * @property int|null $hrv_baseline_high
 * @property int|null $body_battery_high
 * @property int|null $body_battery_low
 * @property int|null $resting_hr
 * @property int|null $stress_avg
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'date',
    'sleep_score',
    'sleep_duration_s',
    'hrv_status',
    'hrv_last_night_ms',
    'hrv_baseline_low',
    'hrv_baseline_high',
    'body_battery_high',
    'body_battery_low',
    'resting_hr',
    'stress_avg',
    'raw',
])]
class WellnessDay extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hrv_status' => HrvStatus::class,
            'raw' => 'array',
        ];
    }
}
