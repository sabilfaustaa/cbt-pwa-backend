<?php

declare(strict_types=1);

use App\Exceptions\JadwalTidakAktifException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\AnalitikController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HasilController;
use App\Http\Controllers\Api\V1\JadwalUjianController;
use App\Http\Controllers\Api\V1\PengawasController;
use App\Http\Controllers\Api\V1\PengumumanController;
use App\Http\Controllers\Api\V1\PesertaController;
use App\Http\Controllers\Api\V1\SesiController;
use App\Http\Controllers\Api\V1\SoalController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

Route::prefix('v1')->group(function () {
    // Health
    Route::get('/health', function () {
        return ApiResponse::success([
            'status' => 'ok',
            'time' => now()->toIso8601String(),
        ]);
    });

    // Auth (tidak butuh middleware — public, throttle login)
    Route::prefix('auth')->middleware('throttle:login')->controller(AuthController::class)->group(function () {
        Route::post('/login', 'login');
        // Alias sesuai konvensi service layer FE (login tetap di-handle method yang sama)
        Route::post('/login/peserta', 'login');
        Route::post('/login/petugas', 'login');
    });

    // Auth (butuh token)
    Route::prefix('auth')->middleware('auth:sanctum')->controller(AuthController::class)->group(function () {
        Route::post('/logout', 'logout');
        Route::get('/me', 'me');
    });

    // User management (admin)
    Route::prefix('users')->middleware(['auth:sanctum', 'role:admin'])->controller(UserController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('/bulk-import-peserta', 'bulkImport');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // Soal management (admin)
    Route::prefix('soal')->middleware(['auth:sanctum', 'role:admin'])->controller(SoalController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('/upload-media', 'uploadMedia');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // Jadwal Ujian (read: admin+pengawas; mutasi: admin)
    Route::prefix('jadwal-ujian')->middleware(['auth:sanctum', 'role:admin,pengawas'])->controller(JadwalUjianController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::get('/{id}/peserta', 'peserta');
        Route::get('/{id}/soal', 'soal');
        Route::get('/{id}/analitik', [AnalitikController::class, 'analitik']);
    });

    Route::prefix('jadwal-ujian')->middleware(['auth:sanctum', 'role:admin'])->controller(JadwalUjianController::class)->group(function () {
        Route::post('/', 'store');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::patch('/{id}/status', 'updateStatus');
        Route::post('/{id}/attach-soal', 'attachSoal');
        Route::delete('/{id}/soal/{soalId}', 'detachSoal');
        Route::put('/{id}/urutan-soal', 'reorderSoal');
        Route::post('/{id}/assign-peserta', 'assignPeserta');
        Route::delete('/{id}/assign-peserta/{userId}', 'unassignPeserta');
    });

    // ── Sesi Ujian (peserta) ────────────────────────────────
    Route::prefix('sesi')->middleware(['auth:sanctum', 'role:peserta', 'throttle:api'])->controller(SesiController::class)->group(function () {
        Route::get('/saya', 'saya');
        Route::post('/mulai', 'mulai');
        Route::get('/{id}/soal', 'soal');
        Route::get('/{id}/soal/{soalId}', 'soalSatuan');
        Route::post('/{id}/selesai', 'selesai');
    });

    // Sesi endpoints dengan rate limit khusus per sesi
    Route::prefix('sesi')->middleware(['auth:sanctum', 'role:peserta'])->controller(SesiController::class)->group(function () {
        Route::put('/{id}/jawaban', 'jawaban')->middleware('throttle:jawaban');
        Route::get('/{id}/heartbeat', 'heartbeat')->middleware('throttle:heartbeat');
        Route::post('/{id}/aktivitas', 'aktivitas')->middleware('throttle:aktivitas');
    });

    // ── Pengawas (monitor + intervensi) ────────────────────────
    Route::prefix('pengawas')->middleware(['auth:sanctum', 'role:admin,pengawas'])->controller(PengawasController::class)->group(function () {
        Route::get('/jadwal/{id}/monitor', 'monitor');
        Route::post('/sesi/{id}/tambah-waktu', 'tambahWaktu');
        Route::post('/sesi/{id}/batalkan', 'batalkan');
    });

    // ── Hasil & Rekap (M13) ─────────────────────────────────
    Route::prefix('sesi')->middleware(['auth:sanctum'])->controller(HasilController::class)->group(function () {
        Route::get('/{id}/hasil', 'hasil');
    });

    Route::prefix('jadwal-ujian')->middleware(['auth:sanctum', 'role:admin,pengawas'])->controller(HasilController::class)->group(function () {
        Route::get('/{id}/rekap', 'rekap');
        Route::get('/{id}/export', 'export');
    });

    // ── Profil & Statistik Peserta (M-B4) ───────────────────────
    Route::prefix('peserta')->middleware(['auth:sanctum', 'role:peserta'])->controller(PesertaController::class)->group(function () {
        Route::get('/statistik', 'statistik');
    });

    // ── Pengumuman (M-B5) ───────────────────────────────────────
    // Baca: semua peran terautentikasi (peserta hanya yang published)
    Route::prefix('pengumuman')->middleware('auth:sanctum')->controller(PengumumanController::class)->group(function () {
        Route::get('/', 'index');
    });
    // Tulis: admin only
    Route::prefix('pengumuman')->middleware(['auth:sanctum', 'role:admin'])->controller(PengumumanController::class)->group(function () {
        Route::post('/', 'store');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // ── Debug routes (hanya untuk testing) ──────────────────
    if (! app()->isProduction()) {
        Route::get('/debug/not-found', fn () => throw new ModelNotFoundException);
        Route::get('/debug/server-error', fn () => throw new RuntimeException('Simulasi error 500'));
        Route::get('/debug/forbidden', fn () => throw new HttpException(403, 'Akses ditolak.'));
        Route::get('/debug/validation', fn (Request $request) => throw new ValidationException(
            validator($request->all(), ['email' => 'required'], ['email.required' => 'Email wajib diisi.'])
        ));
        Route::get('/debug/jadwal-tidak-aktif', fn () => throw new JadwalTidakAktifException);
    }
});
