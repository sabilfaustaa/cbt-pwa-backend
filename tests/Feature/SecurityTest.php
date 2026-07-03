<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\JadwalPeserta;
use App\Models\JadwalUjian;
use App\Models\Role;
use App\Models\SesiUjian;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $pesertaRoleId = Role::where('nama_role', RoleName::Peserta->value)->value('id');
    $adminRoleId = Role::where('nama_role', RoleName::Admin->value)->value('id');

    $pesertaList = User::where('role_id', $pesertaRoleId)->get();
    $this->peserta = $pesertaList->first();
    $this->pesertaLain = $pesertaList->skip(1)->first();
    $this->admin = User::where('role_id', $adminRoleId)->first();

    // Pin ke jadwal MTK (berlangsung) — `first()` tanpa orderBy di PostgreSQL
    // bisa me-resolve sesi/token jadwal lain yang belum_mulai (akar F-05).
    $this->jadwal = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->firstOrFail();
    $this->sesi = SesiUjian::where('user_id', $this->peserta->id)
        ->where('jadwal_ujian_id', $this->jadwal->id)
        ->firstOrFail();
    $this->sesiLain = SesiUjian::where('user_id', $this->pesertaLain->id)
        ->where('jadwal_ujian_id', $this->jadwal->id)
        ->firstOrFail();

    // Mulai sesi peserta pertama agar bisa akses endpoint soal
    $tokenAkses = JadwalPeserta::where('user_id', $this->peserta->id)
        ->where('jadwal_ujian_id', $this->jadwal->id)
        ->value('token_akses');
    $this->actingAs($this->peserta, 'sanctum')
        ->postJson('/api/v1/sesi/mulai', ['token_akses' => $tokenAkses])
        ->assertStatus(200);

    $this->sesi->refresh();
    $this->flushHeaders();
});

// ─── (a) Proteksi Kunci Jawaban ───────────────────────────────────────────────

test('GET /sesi/:id/soal tidak pernah bocorkan is_kunci', function () {
    $res = $this->actingAs($this->peserta, 'sanctum')
        ->getJson("/api/v1/sesi/{$this->sesi->id}/soal");

    $res->assertStatus(200);

    $soalList = $res->json('data.soal');
    expect($soalList)->toBeArray()->not->toBeEmpty();

    foreach ($soalList as $soal) {
        // Field sensitif harus ABSEN dari setiap soal
        expect($soal)->not->toHaveKey('is_kunci');
        expect($soal)->not->toHaveKey('jawaban_benar_bool');
        expect($soal)->not->toHaveKey('pembahasan');

        // Opsi (jika ada) juga tidak boleh punya is_kunci atau nomor_urut
        if (isset($soal['opsi'])) {
            foreach ($soal['opsi'] as $opsi) {
                expect($opsi)->not->toHaveKey('is_kunci');
                expect($opsi)->not->toHaveKey('nomor_urut');
            }
        }
    }
});

test('GET /sesi/:id/soal/:soal_id tidak bocorkan is_kunci', function () {
    // Ambil soal pertama dari jadwal
    $soalId = $this->jadwal->soal()->first()?->id;
    if (! $soalId) {
        $this->markTestSkipped('Tidak ada soal di jadwal.');
    }

    $res = $this->actingAs($this->peserta, 'sanctum')
        ->getJson("/api/v1/sesi/{$this->sesi->id}/soal/{$soalId}");

    $res->assertStatus(200);

    $soal = $res->json('data.soal');
    expect($soal)->not->toHaveKey('is_kunci');
    expect($soal)->not->toHaveKey('jawaban_benar_bool');
    expect($soal)->not->toHaveKey('pembahasan');

    if (isset($soal['opsi'])) {
        foreach ($soal['opsi'] as $opsi) {
            expect($opsi)->not->toHaveKey('is_kunci');
            expect($opsi)->not->toHaveKey('nomor_urut');
        }
    }
});

// ─── (b) Ownership — peserta B tidak bisa akses sesi peserta A ────────────────

test('GET /sesi/:id/soal peserta lain return 403', function () {
    $res = $this->actingAs($this->pesertaLain, 'sanctum')
        ->getJson("/api/v1/sesi/{$this->sesi->id}/soal");

    $res->assertStatus(403);
});

test('PUT /sesi/:id/jawaban peserta lain return 403', function () {
    $res = $this->actingAs($this->pesertaLain, 'sanctum')
        ->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
            'soal_id' => 1,
            'opsi_id' => 1,
        ]);

    $res->assertStatus(403);
});

test('GET /sesi/:id/heartbeat peserta lain return 403', function () {
    $res = $this->actingAs($this->pesertaLain, 'sanctum')
        ->getJson("/api/v1/sesi/{$this->sesi->id}/heartbeat");

    $res->assertStatus(403);
});

test('POST /sesi/:id/selesai peserta lain return 403', function () {
    $res = $this->actingAs($this->pesertaLain, 'sanctum')
        ->postJson("/api/v1/sesi/{$this->sesi->id}/selesai");

    $res->assertStatus(403);
});

// ─── (c) CORS whitelist ────────────────────────────────────────────────────────

test('CORS: OPTIONS request ke /api/v1/health mengandung header yang benar', function () {
    $res = $this->options('/api/v1/health', [], [
        'Origin' => 'http://localhost:5173',
        'Access-Control-Request-Method' => 'GET',
    ]);

    // Respons CORS harus ada header Access-Control-Allow-Origin
    $origin = $res->headers->get('Access-Control-Allow-Origin');
    expect($origin)->not->toBeNull();
});

test('CORS config: allowed_origins mengandung localhost dev server', function () {
    $allowedOrigins = config('cors.allowed_origins');

    expect($allowedOrigins)->toBeArray();

    // Salah satu dari origins harus mengizinkan localhost dev (5173 atau 9000)
    $hasLocalhost = collect($allowedOrigins)->some(
        fn ($o) => str_contains((string) $o, 'localhost') || $o === '*'
    );
    expect($hasLocalhost)->toBeTrue();
});

// ─── (d) Audit log aktif ──────────────────────────────────────────────────────

test('Login admin mencatat audit auth.login.success', function () {
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ])->assertStatus(200);

    $audit = AuditLog::where('action', 'auth.login.success')->latest()->first();
    expect($audit)->not->toBeNull();
    expect($audit->user_id)->not->toBeNull();
});

test('Login gagal mencatat audit auth.login.failed', function () {
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'salah',
    ])->assertStatus(401);

    $audit = AuditLog::where('action', 'auth.login.failed')->latest()->first();
    expect($audit)->not->toBeNull();
    expect($audit->user_id)->toBeNull(); // gagal login → tidak ada user_id
});

// ─── Rate limit: jawaban fungsional ──────────────────────────────────────────

test('Jawaban rate limit: throttle middleware terdaftar dan bekerja dengan limit kecil', function () {
    $sesiId = $this->sesi->id;
    $key = 'jawaban:'.$sesiId;

    // Bersihkan bucket & pasang limit rendah (3) untuk test ini
    RateLimiter::clear($key);
    RateLimiter::for('jawaban', fn () => Limit::perMinute(3)->by($key));

    // 3 request pertama → tidak 429
    for ($i = 0; $i < 3; $i++) {
        $res = $this->actingAs($this->peserta, 'sanctum')
            ->putJson("/api/v1/sesi/{$sesiId}/jawaban", []);

        expect($res->status())->not->toBe(429);
    }

    // Request ke-4 → 429 Too Many Requests
    $res = $this->actingAs($this->peserta, 'sanctum')
        ->putJson("/api/v1/sesi/{$sesiId}/jawaban", []);

    $res->assertStatus(429);

    // Restore limit normal setelah test
    RateLimiter::for('jawaban', fn (Request $req) => Limit::perMinute(60)
        ->by('jawaban:'.($req->route('id') ?? $req->ip())));
});
