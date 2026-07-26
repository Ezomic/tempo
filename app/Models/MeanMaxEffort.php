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
 * @property Sport $sport
 * @property int $duration_s
 * @property float $speed_mps
 * @property int $activity_id
 * @property Carbon $achieved_on
 */
#[Fillable([
    'user_id',
    'sport',
    'duration_s',
    'speed_mps',
    'activity_id',
    'achieved_on',
])]
class MeanMaxEffort extends Model
{
    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sport' => Sport::class,
            'speed_mps' => 'float',
            'achieved_on' => 'date',
        ];
    }
}
