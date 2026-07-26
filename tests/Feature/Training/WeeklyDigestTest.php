<?php

declare(strict_types=1);

use App\Actions\BuildWeeklyDigestAction;
use App\Enums\Sport;
use App\Mail\WeeklyDigestMail;
use App\Models\Activity;
use App\Models\PersonalRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

it('summarises the week just finished with real values', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-20'); // Monday
    $lastWeek = CarbonImmutable::parse('2026-07-15'); // Wed of prior week

    Activity::create([
        'user_id' => $user->id,
        'external_id' => 'a1',
        'sport' => Sport::Run,
        'started_at' => $lastWeek,
        'trimp' => 70,
    ]);
    $activity = Activity::create([
        'user_id' => $user->id,
        'external_id' => 'a2',
        'sport' => Sport::Bike,
        'started_at' => $lastWeek->addDay(),
        'trimp' => 40,
    ]);
    PersonalRecord::create([
        'user_id' => $user->id,
        'distance_m' => 5000,
        'duration_s' => 1400,
        'activity_id' => $activity->id,
        'achieved_on' => $lastWeek->toDateString(),
    ]);

    $digest = app(BuildWeeklyDigestAction::class)->handle($user, $today);

    expect($digest['has_activity'])->toBeTrue()
        ->and($digest['sessions'])->toBe(2)
        ->and($digest['load']['total'])->toBe(110)
        ->and($digest['prs'])->toHaveCount(1)
        ->and($digest['prs'][0]['label'])->toBe('5K')
        ->and($digest['prs'][0]['time'])->toBe('23:20');
});

it('produces a quiet-week digest when nothing was trained', function () {
    $user = User::factory()->create();

    $digest = app(BuildWeeklyDigestAction::class)->handle($user, CarbonImmutable::parse('2026-07-20'));

    expect($digest['has_activity'])->toBeFalse()
        ->and($digest['sessions'])->toBe(0);
});

it('sends the digest to each user on command', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->artisan('tempo:weekly-digest')->assertExitCode(0);

    Mail::assertSent(WeeklyDigestMail::class, fn (WeeklyDigestMail $mail): bool => $mail->hasTo($user->email));
});

it('targets a single user with the --user option', function () {
    Mail::fake();
    $target = User::factory()->create();
    User::factory()->create();

    $this->artisan("tempo:weekly-digest --user={$target->id}")->assertExitCode(0);

    Mail::assertSent(WeeklyDigestMail::class, 1);
    Mail::assertSent(WeeklyDigestMail::class, fn (WeeklyDigestMail $mail): bool => $mail->hasTo($target->email));
});
