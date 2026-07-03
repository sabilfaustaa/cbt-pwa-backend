<?php

use App\Exceptions\JadwalTidakAktifException;
use App\Helpers\ApiResponse;
use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class]);
        // Rate limiters didefinisikan di AppServiceProvider::boot()
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Selalu render JSON untuk API routes
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->wantsJson(),
        );

        // Tangkap NotFoundHttpException (route tidak ada / model tidak ditemukan)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $message = $e->getPrevious() instanceof ModelNotFoundException
                ? 'Data tidak ditemukan.'
                : 'Endpoint tidak ditemukan.';

            return ApiResponse::error($message, 404);
        });

        // Tangkap AuthenticationException → 401
        $exceptions->render(
            fn (AuthenticationException $e, Request $request) => ApiResponse::error('Tidak terautentikasi.', 401)
        );

        // Tangkap ValidationException → 422 dengan errors detail
        $exceptions->render(function (ValidationException $e, Request $request) {
            return ApiResponse::error('Validasi gagal.', 422, $e->errors());
        });

        // Tangkap custom JadwalTidakAktifException
        $exceptions->render(
            fn (JadwalTidakAktifException $e, Request $request) => ApiResponse::error($e->getMessage(), $e->getCode() ?: 422)
        );

        // Tangkap HttpException umum (403, 405, etc.)
        $exceptions->render(function (HttpException $e, Request $request) {
            $message = $e->getMessage() ?: 'HTTP Error '.$e->getStatusCode();

            return ApiResponse::error($message, $e->getStatusCode());
        });

        // Fallback: semua exception lain → 500 (production-safe, tidak bocorkan detail)
        $exceptions->render(function (Throwable $e, Request $request) {
            Log::error($e->getMessage(), ['exception' => $e]);

            $message = config('app.debug')
                ? $e->getMessage()
                : 'Terjadi kesalahan pada server.';

            return ApiResponse::error($message, 500);
        });
    })->create();
