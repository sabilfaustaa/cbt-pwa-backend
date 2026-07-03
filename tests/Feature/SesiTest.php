<?php

use App\Enums\StatusJadwal;
use App\Enums\StatusSesi;
use App\Models\JadwalPeserta;
use App\Models\JadwalUjian;
use App\Models\SesiUjian;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->peserta = User::where('role_id', 3)->first();

    // Pin ke jadwal MTK (berlangsung) — `first()` tanpa orderBy di PostgreSQL
    // bisa me-resolve sesi/token jadwal lain yang belum_mulai (akar F-05).
    $jadwalMtkId = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->value('id');
    $this->tokenAkses = JadwalPeserta::where('user_id', $this->peserta->id)
        ->where('jadwal_ujian_id', $jadwalMtkId)
        ->value('token_akses');
    $this->pesertaToken = $this->peserta->createToken('test')->plainTextToken;
    $this->sesi = SesiUjian::where('user_id', $this->peserta->id)
        ->where('jadwal_ujian_id', $jadwalMtkId)
        ->firstOrFail();

    $this->flushHeaders();
});

// ─── POST /sesi/mulai ────────────────────────────────────────

test('POST mulai sesi return 200 & set status sedang_berlangsung', function () {
    $res = $this->withToken($this->pesertaToken)->postJson('/api/v1/sesi/mulai', [
        'token_akses' => $this->tokenAkses,
    ]);

    $res->assertStatus(200);
    expect($res->json('data.sesi.status'))->toBe('sedang_berlangsung');
    expect($res->json('data.sesi.waktu_mulai'))->not->toBeNull();
    expect($res->json('data.sesi.waktu_batas'))->not->toBeNull();
    expect($res->json('data.sisa_detik'))->toBeInt()->toBeGreaterThan(0);
    expect($res->json('data.soal_count'))->toBeInt()->toBeGreaterThan(0);
    expect($res->json('data.server_time'))->toBeString();

    $this->sesi->refresh();
    expect($this->sesi->status)->toBe(StatusSesi::SedangBerlangsung);
    expect($this->sesi->waktu_mulai)->not->toBeNull();
});

test('POST mulai idempotent: panggil 2x tetap 200', function () {
    $token = $this->pesertaToken;

    $this->withToken($token)->postJson('/api/v1/sesi/mulai', [
        'token_akses' => $this->tokenAkses,
    ])->assertStatus(200);

    $res = $this->withToken($token)->postJson('/api/v1/sesi/mulai', [
        'token_akses' => $this->tokenAkses,
    ])->assertStatus(200);

    expect($res->json('data.sesi.status'))->toBe('sedang_berlangsung');
});

test('POST mulai tanpa token_akses return 422', function () {
    $res = $this->withToken($this->pesertaToken)->postJson('/api/v1/sesi/mulai', []);

    $res->assertStatus(422);
    expect($res->json('meta.errors.token_akses'))->not->toBeNull();
});

test('POST mulai dengan token akses tidak valid return 403', function () {
    $res = $this->withToken($this->pesertaToken)->postJson('/api/v1/sesi/mulai', [
        'token_akses' => str_repeat('0', 64),
    ]);

    $res->assertStatus(403);
});

test('POST mulai jadwal draft return 409', function () {
    $jadwalDraft = JadwalUjian::where('status', StatusJadwal::Draft)->first();
    if (! $jadwalDraft) {
        $this->markTestSkipped('No draft jadwal in seeder');
    }
    $jadwalPeserta = JadwalPeserta::where('jadwal_ujian_id', $jadwalDraft->id)->first();
    if (! $jadwalPeserta) {
        $this->markTestSkipped('No peserta in draft jadwal');
    }

    $peserta = User::find($jadwalPeserta->user_id);
    $token = $peserta->createToken('test')->plainTextToken;

    $res = $this->withToken($token)->postJson('/api/v1/sesi/mulai', [
        'token_akses' => $jadwalPeserta->token_akses,
    ]);

    $res->assertStatus(409);
});

// ─── GET /sesi/saya ──────────────────────────────────────────

test('GET saya return jadwal_aktif & riwayat', function () {
    $res = $this->withToken($this->pesertaToken)->getJson('/api/v1/sesi/saya');

    $res->assertStatus(200);
    expect($res->json('data.jadwal_aktif'))->toBeArray();
    expect($res->json('data.riwayat'))->toBeArray();

    // Peserta seeder punya 10 sesi (semua belum_mulai → jadwal_aktif)
    expect(count($res->json('data.jadwal_aktif')))->toBeGreaterThan(0);

    foreach ($res->json('data.jadwal_aktif') as $item) {
        expect($item)->toHaveKey('sesi_id');
        expect($item)->toHaveKey('jadwal');
        expect($item)->toHaveKey('token_akses');
    }
});

// ─── GET /sesi/:id/soal ──────────────────────────────────────

test('GET soal return list soal after mulai', function () {
    $this->withToken($this->pesertaToken)->postJson('/api/v1/sesi/mulai', [
        'token_akses' => $this->tokenAkses,
    ])->assertStatus(200);

    $res = $this->withToken($this->pesertaToken)->getJson("/api/v1/sesi/{$this->sesi->id}/soal");

    $res->assertStatus(200);
    expect($res->json('data.soal'))->toBeArray();
    expect($res->json('data.sisa_detik'))->toBeInt();
    expect($res->json('data.server_time'))->toBeString();

    $soal = $res->json('data.soal.0');
    expect($soal)->toHaveKey('id');
    expect($soal)->toHaveKey('tipe');
    expect($soal)->toHaveKey('pertanyaan');
    expect($soal)->toHaveKey('poin');
    expect($soal)->toHaveKey('nomor_urut');

    // Kunci jawaban TIDAK BOLEH muncul
    expect($soal)->not->toHaveKey('is_kunci');
    expect($soal)->not->toHaveKey('jawaban_benar_bool');
    expect($soal)->not->toHaveKey('pembahasan');
});

test('GET soal protected: peserta lain tidak boleh akses', function () {
    $otherPeserta = User::where('role_id', 3)->skip(1)->first();
    $otherToken = $otherPeserta->createToken('test')->plainTextToken;

    $res = $this->withToken($otherToken)->getJson("/api/v1/sesi/{$this->sesi->id}/soal");

    $res->assertStatus(403);
});

test('F-02: GET soal sesi belum_mulai return 409 — soal tidak bocor', function () {
    // Replika temuan audit: sesi BIND belum_mulai (jadwal baru dibuka +4 jam).
    // Sebelum fix, endpoint membocorkan seluruh soal sebelum jadwal dibuka.
    $jadwalBindId = JadwalUjian::where('kode_jadwal', 'UAS-2026-BIND')->value('id');
    $sesiBind = SesiUjian::where('user_id', $this->peserta->id)
        ->where('jadwal_ujian_id', $jadwalBindId)
        ->firstOrFail();
    expect($sesiBind->status->value)->toBe('belum_mulai');

    $res = $this->withToken($this->pesertaToken)->getJson("/api/v1/sesi/{$sesiBind->id}/soal");

    $res->assertStatus(409);
    expect($res->json('data.soal'))->toBeNull();
});

test('F-02: GET soal satuan sesi belum_mulai return 409', function () {
    $jadwalBindId = JadwalUjian::where('kode_jadwal', 'UAS-2026-BIND')->value('id');
    $sesiBind = SesiUjian::where('user_id', $this->peserta->id)
        ->where('jadwal_ujian_id', $jadwalBindId)
        ->firstOrFail();
    $soalId = $sesiBind->jadwalUjian->soal()->first()->id;

    $res = $this->withToken($this->pesertaToken)->getJson("/api/v1/sesi/{$sesiBind->id}/soal/{$soalId}");

    $res->assertStatus(409);
});

test('F-02: GET soal sesi selesai return 409 — review lewat /hasil', function () {
    $this->withToken($this->pesertaToken)->postJson('/api/v1/sesi/mulai', [
        'token_akses' => $this->tokenAkses,
    ])->assertStatus(200);
    $this->withToken($this->pesertaToken)->postJson("/api/v1/sesi/{$this->sesi->id}/selesai")
        ->assertStatus(200);

    $res = $this->withToken($this->pesertaToken)->getJson("/api/v1/sesi/{$this->sesi->id}/soal");

    $res->assertStatus(409);
    expect($res->json('data.soal'))->toBeNull();
});

// ─── GET /sesi/:id/soal/:soalId ──────────────────────────────

test('GET soal satuan return satu soal', function () {
    $this->withToken($this->pesertaToken)->postJson('/api/v1/sesi/mulai', [
        'token_akses' => $this->tokenAkses,
    ])->assertStatus(200);

    $soalRes = $this->withToken($this->pesertaToken)->getJson("/api/v1/sesi/{$this->sesi->id}/soal");
    $soalId = $soalRes->json('data.soal.0.id');

    $res = $this->withToken($this->pesertaToken)->getJson("/api/v1/sesi/{$this->sesi->id}/soal/{$soalId}");
    $res->assertStatus(200);
    expect($res->json('data.soal.id'))->toBe($soalId);
    expect($res->json('data.soal'))->not->toHaveKey('is_kunci');
});

test('GET soal satuan not found', function () {
    $this->withToken($this->pesertaToken)->postJson('/api/v1/sesi/mulai', [
        'token_akses' => $this->tokenAkses,
    ]);

    $res = $this->withToken($this->pesertaToken)->getJson("/api/v1/sesi/{$this->sesi->id}/soal/99999");
    $res->assertStatus(404);
});

// ─── Edge cases ──────────────────────────────────────────────

test('POST mulai tanpa token auth return 401', function () {
    $this->postJson('/api/v1/sesi/mulai', [
        'token_akses' => $this->tokenAkses,
    ])->assertStatus(401);
});

test('Admin tidak bisa akses sesi endpoints', function () {
    $admin = User::where('email', 'admin@cbt.test')->first();
    $adminToken = $admin->createToken('test')->plainTextToken;

    $this->withToken($adminToken)->getJson('/api/v1/sesi/saya')->assertStatus(403);
    $this->withToken($adminToken)->getJson("/api/v1/sesi/{$this->sesi->id}/soal")->assertStatus(403);
});
