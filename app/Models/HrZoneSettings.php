<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $max_hr
 * @property int|null $resting_hr
 * @property int|null $lthr
 * @property array<int, int>|null $zone_boundaries
 * @property string $sex
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'max_hr',
    'resting_hr',
    'lthr',
    'zone_boundaries',
    'sex',
])]
class HrZoneSettings extends Model
{
    protected $table = 'hr_zone_settings';

    public const SEX_MALE = 'male';

    public const SEX_FEMALE = 'female';

    public function hasHeartRateRange(): bool
    {
        return $this->max_hr !== null && $this->resting_hr !== null;
    }

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
            'zone_boundaries' => 'array',
        ];
    }
}
