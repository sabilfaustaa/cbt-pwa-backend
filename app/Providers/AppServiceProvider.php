<?php

namespace App\Providers;

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

    /**
     * Bootstrap any application services.
     * Rate limiter didefinisikan di sini agar facade sudah siap saat dipanggil.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Saat unit testing, gunakan limit sangat tinggi agar tidak mengganggu suite
        // Kebenaran fungsional rate limit diverifikasi di SecurityTest secara eksplisit.
        $inTest = $this->app->runningUnitTests();
        $loginLimit = $inTest ? 9999 : 5;
        $jawabanLimit = $inTest ? 9999 : 60;
        $heartbeatLimit = $inTest ? 9999 : 120;
        $aktivitasLimit = $inTest ? 9999 : 60;
        $apiLimit = $inTest ? 9999 : 60;

        // Login: 5 req/menit per IP
        RateLimiter::for(
            'login',
            fn (Request $req) => Limit::perMinute($loginLimit)->by($req->ip())
        );

        // Jawaban: 60 req/menit per sesi (key = sesi ID dari route param)
        RateLimiter::for(
            'jawaban',
            fn (Request $req) => Limit::perMinute($jawabanLimit)
                ->by('jawaban:'.($req->route('id') ?? $req->ip()))
        );

        // Heartbeat: 120 req/menit per sesi
        RateLimiter::for(
            'heartbeat',
            fn (Request $req) => Limit::perMinute($heartbeatLimit)
                ->by('heartbeat:'.($req->route('id') ?? $req->ip()))
        );

        // Aktivitas: 60 req/menit per sesi
        RateLimiter::for(
            'aktivitas',
            fn (Request $req) => Limit::perMinute($aktivitasLimit)
                ->by('aktivitas:'.($req->route('id') ?? $req->ip()))
        );

        // Default API: 60 req/menit per user (atau IP jika tidak login)
        RateLimiter::for(
            'api',
            fn (Request $req) => Limit::perMinute($apiLimit)
                ->by(optional($req->user())->id ?: $req->ip())
        );
    }
}
