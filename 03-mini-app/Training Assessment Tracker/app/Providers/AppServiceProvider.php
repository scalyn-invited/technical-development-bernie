<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Login throttling, keyed on email *and* IP rather than IP alone.
     *
     * A plain `throttle:5,1` counts every attempt from an address, successful
     * ones included. This tool is internal, so its users share one office IP:
     * five sign-ins on a Monday morning would lock out the sixth colleague, and
     * one person guessing at their own password would lock out the team. Keying
     * on the email as well confines the lockout to the account actually under
     * attack, which is the account the limit is meant to protect.
     *
     * The IP stays in the key so that an attacker cycling through many emails
     * from one address is still bounded.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by(mb_strtolower($email).'|'.$request->ip());
        });
    }
}
