<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;

/**
 * id-client signs every user in with remember-me, so a browser stays signed in
 * to tempo until the user signs out, here or at Thijssensoftware ID (THI-368).
 */
const TEMPO_LOGOUT_SECRET = 'test-logout-secret';

/**
 * Signs an existing user in through the ID callback and returns the
 * remember-me cookie the callback set.
 *
 * @return array{string, string}
 */
function signInThroughId(): array
{
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn((new SocialiteUser)->map([
        'id' => '42',
        'name' => 'Robbin Thijssen',
        'email' => 'robbin@example.test',
    ]));
    Socialite::shouldReceive('driver')->with('thijssensoftware')->andReturn($provider);

    $recaller = Auth::guard()->getRecallerName();
    $cookie = test()->get(route('sso.callback'))->assertRedirect()->getCookie($recaller);

    expect($cookie)->not->toBeNull();

    return [$recaller, (string) $cookie?->getValue()];
}

/**
 * A browser coming back after its session expired, carrying nothing but the
 * remember-me cookie.
 *
 * @return TestResponse<Response>
 */
function returnWithRememberCookie(string $recaller, string $value): TestResponse
{
    Auth::forgetGuards();
    test()->flushSession();

    return test()->withCookie($recaller, $value)->get(route('dashboard'));
}

it('keeps a browser signed in through the remember-me cookie after an ID sign-in', function () {
    $user = User::factory()->create(['email' => 'robbin@example.test']);

    [$recaller, $value] = signInThroughId();

    returnWithRememberCookie($recaller, $value)->assertOk();

    assertAuthenticatedAs($user->refresh());
});

it('refuses the remember-me cookie once ID signs the user out', function () {
    config(['id-client.logout_secret' => TEMPO_LOGOUT_SECRET]);

    User::factory()->create(['email' => 'robbin@example.test']);

    [$recaller, $value] = signInThroughId();

    returnWithRememberCookie($recaller, $value)->assertOk();

    $body = json_encode(['sub' => '42', 'issued_at' => Carbon::now()->getTimestamp()], JSON_THROW_ON_ERROR);

    $this->call('POST', route('sso.logout'), server: [
        'HTTP_X_ID_SIGNATURE' => hash_hmac('sha256', $body, TEMPO_LOGOUT_SECRET),
        'CONTENT_TYPE' => 'application/json',
    ], content: $body)->assertOk();

    // Later, so the cookie itself is refused rather than a same-second stamp
    // ending the session it restores.
    $this->travel(1)->minute();

    returnWithRememberCookie($recaller, $value)->assertRedirect(route('login'));

    assertGuest();
});
