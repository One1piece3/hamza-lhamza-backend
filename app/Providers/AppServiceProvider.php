<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request) {
            $email = (string) $request->input('email', 'guest');

            return [
                Limit::perMinute(5)->by($request->ip() . '|' . $email),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('auth-forgot-password', function (Request $request) {
            $email = (string) $request->input('email', 'guest');

            return [
                Limit::perMinute(3)->by($request->ip() . '|' . $email),
                Limit::perMinute(10)->by($request->ip()),
            ];
        });

        RateLimiter::for('auth-reset-password', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
            ];
        });

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            $requestedFrontendUrl = trim((string) request()?->input('frontend_url', ''));
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

            if ($requestedFrontendUrl !== '') {
                $host = parse_url($requestedFrontendUrl, PHP_URL_HOST);

                if (!in_array($host, ['localhost', '127.0.0.1'], true)) {
                    $frontendUrl = rtrim($requestedFrontendUrl, '/');
                }
            }

            return $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
        });
    }
}
