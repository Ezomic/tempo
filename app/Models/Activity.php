<?php

namespace App\Models;

use App\Enums\Sport;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $external_id
 * @property Sport $sport
 * @property string|null $sub_sport
 * @property Carbon $started_at
 * @property string|null $timezone
 * @property int|null $duration_s
 * @property int|null $moving_time_s
 * @property float|null $distance_m
 * @property int|null $avg_hr
 * @property int|null $max_hr
 * @property float|null $elevation_gain_m
 * @property float|null $avg_speed_mps
 * @property int|null $calories
 * @property float|null $trimp
 * @property array<int, int>|null $hr_zone_seconds
 * @property string|null $fit_path
 * @property string|null $streams_path
 * @property array<string, mixed>|null $raw_summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'external_id',
    'sport',
    'sub_sport',
    'started_at',
    'timezone',
    'duration_s',
    'moving_time_s',
    'distance_m',
    'avg_hr',
    'max_hr',
    'elevation_gain_m',
    'avg_speed_mps',
    'calories',
    'trimp',
    'hr_zone_seconds',
    'fit_path',
    'streams_path',
    'raw_summary',
])]
class Activity extends Model
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
            'sport' => Sport::class,
            'started_at' => 'datetime',
            'distance_m' => 'float',
            'elevation_gain_m' => 'float',
            'avg_speed_mps' => 'float',
            'trimp' => 'float',
            'hr_zone_seconds' => 'array',
            'raw_summary' => 'array',
        ];
    }
}
