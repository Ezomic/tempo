<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $distance_m
 * @property int $duration_s
 * @property int $activity_id
 * @property Carbon $achieved_on
 */
#[Fillable([
    'user_id',
    'distance_m',
    'duration_s',
    'activity_id',
    'achieved_on',
])]
class PersonalRecord extends Model
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
            'achieved_on' => 'date',
        ];
    }
}
