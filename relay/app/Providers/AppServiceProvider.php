<?php

namespace App\Providers;

use App\Http\Requests\RedeemPairingCodeRequest;
use App\Logging\ScrubRequestData;
use App\Support\SecretHasher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SecretHasher::class, fn () => new SecretHasher(
            (string) config('app.key'),
        ));
    }

    public function boot(): void
    {
        $this->scrubEveryLogChannel();
        $this->configureRateLimiting();
    }

    /**
     * Applies the scrubbing tap to every configured channel rather than listing
     * them in config/logging.php, so a channel added later cannot silently
     * bypass the metadata-only policy.
     */
    private function scrubEveryLogChannel(): void
    {
        $channels = config('logging.channels', []);

        foreach (array_keys($channels) as $name) {
            $taps = config("logging.channels.{$name}.tap", []);

            if (! in_array(ScrubRequestData::class, $taps, true)) {
                $taps[] = ScrubRequestData::class;
            }

            config(["logging.channels.{$name}.tap" => $taps]);
        }
    }

    /**
     * Every limiter below is keyed on the credential being presented, never on
     * the caller's IP address — this app reads no IPs at all.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('access-requests', fn (Request $request) => Limit::perHour(5)
            ->by('access-request')
            ->response(fn () => response()->json(['error' => 'Too many requests.'], 429)));

        // Both limits are returned together so the throttle middleware counts
        // BOTH on every attempt: a per-code-prefix limit stops hammering one
        // code, and a global limit stops an attacker walking the code space by
        // spreading attempts across prefixes.
        RateLimiter::for('pair', fn (Request $request) => [
            Limit::perHour(5)
                ->by(RedeemPairingCodeRequest::rateLimitKeyFor($request))
                ->response($this->tooManyPairingAttempts(...)),
            Limit::perHour(30)
                ->by('pair-global')
                ->response($this->tooManyPairingAttempts(...)),
        ]);
    }

    private function tooManyPairingAttempts(): JsonResponse
    {
        return response()->json(['error' => 'Too many pairing attempts.'], 429);
    }
}
