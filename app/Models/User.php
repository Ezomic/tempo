<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property float|null $home_lat
 * @property float|null $home_lng
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'home_lat', 'home_lng'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return HasOne<GarminConnection, $this>
     */
    public function garminConnection(): HasOne
    {
        return $this->hasOne(GarminConnection::class);
    }

    /**
     * @return HasOne<HrZoneSettings, $this>
     */
    public function hrZoneSettings(): HasOne
    {
        return $this->hasOne(HrZoneSettings::class);
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * @return HasMany<WellnessDay, $this>
     */
    public function wellnessDays(): HasMany
    {
        return $this->hasMany(WellnessDay::class);
    }

    /**
     * @return HasMany<PlannedWorkout, $this>
     */
    public function plannedWorkouts(): HasMany
    {
        return $this->hasMany(PlannedWorkout::class);
    }

    /**
     * @return HasMany<DailyLoadMetric, $this>
     */
    public function dailyLoadMetrics(): HasMany
    {
        return $this->hasMany(DailyLoadMetric::class);
    }

    /**
     * @return HasMany<PersonalRecord, $this>
     */
    public function personalRecords(): HasMany
    {
        return $this->hasMany(PersonalRecord::class);
    }

    /**
     * @return HasMany<MeanMaxEffort, $this>
     */
    public function meanMaxEfforts(): HasMany
    {
        return $this->hasMany(MeanMaxEffort::class);
    }

    /**
     * @return HasMany<TrainingGoal, $this>
     */
    public function trainingGoals(): HasMany
    {
        return $this->hasMany(TrainingGoal::class);
    }

    /**
     * @return HasMany<LifeEvent, $this>
     */
    public function lifeEvents(): HasMany
    {
        return $this->hasMany(LifeEvent::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'home_lat' => 'float',
            'home_lng' => 'float',
        ];
    }
}
