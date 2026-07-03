<?php

use App\Console\Commands\AutoSubmitSesi;
use App\Enums\RoleName;
use App\Enums\StatusSesi;
use App\Models\AuditLog;
use App\Models\JadwalPeserta;
use App\Models\JadwalUjian;
use App\Models\Role;
use App\Models\SesiUjian;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $pesertaRoleId = Role::where('nama_role', RoleName::Peserta->value)->value('id');
    $this->peserta = User::where('role_id', $pesertaRoleId)->first();

    // Pin ke jadwal MTK (berlangsung) — `first()` tanpa orderBy di PostgreSQL
    // bisa me-resolve sesi/token jadwal lain yang belum_mulai (akar F-05).
    $jadwalMtkId = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->value('id');
    $this->sesi = SesiUjian::where('user_id', $this->peserta->id)
        ->where('jadwal_ujian_id', $jadwalMtkId)
        ->firstOrFail();

    // Mulai sesi agar statusnya sedang_berlangsung
    $tokenAkses = JadwalPeserta::where('user_id', $this->peserta->id)
        ->where('jadwal_ujian_id', $jadwalMtkId)
        ->value('token_akses');
    $this->actingAs($this->peserta, 'sanctum')
        ->postJson('/api/v1/sesi/mulai', ['token_akses' => $tokenAkses])
        ->assertStatus(200);

    $this->sesi->refresh();
    $this->flushHeaders();
});

// ─── sesi:auto-submit command ─────────────────────────────────────────────────

test('command: sesi lewat batas → status kadaluarsa + skor terisi', function () {
    // Paksa waktu_batas ke masa lalu agar sesi dianggap kadaluarsa
    $this->sesi->update(['waktu_batas' => Carbon::now()->subMinutes(10)]);

    $this->artisan(AutoSubmitSesi::class)->assertSuccessful();

    $this->sesi->refresh();
    expect($this->sesi->status)->toBe(StatusSesi::Kadaluarsa);
    expect($this->sesi->waktu_selesai)->not->toBeNull();
    expect($this->sesi->skor_total)->toBeNumeric();
});

test('command: sesi lewat batas → audit sesi.kadaluarsa tercatat', function () {
    $this->sesi->update(['waktu_batas' => Carbon::now()->subMinutes(5)]);

    $this->artisan(AutoSubmitSesi::class)->assertSuccessful();

    $audit = AuditLog::where('action', 'sesi.kadaluarsa')
        ->where('entity_id', $this->sesi->id)
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->metadata['skor_total'])->toBeNumeric();
});

test('command: sesi belum lewat batas → tidak tersentuh', function () {
    // waktu_batas masih di masa depan (default dari mulai sesi)
    expect($this->sesi->waktu_batas)->toBeGreaterThan(now());

    $this->artisan(AutoSubmitSesi::class)->assertSuccessful();

    $this->sesi->refresh();
    expect($this->sesi->status)->toBe(StatusSesi::SedangBerlangsung);
    expect($this->sesi->waktu_selesai)->toBeNull();
});

test('command: sesi sudah selesai → tidak diproses ulang', function () {
    // Selesaikan sesi secara normal
    $this->actingAs($this->peserta, 'sanctum')
        ->postJson("/api/v1/sesi/{$this->sesi->id}/selesai")
        ->assertStatus(200);

    $this->sesi->refresh();
    expect($this->sesi->status)->toBe(StatusSesi::Selesai);

    // Paksa waktu_batas ke masa lalu, tapi status bukan sedang_berlangsung
    $this->sesi->update(['waktu_batas' => Carbon::now()->subMinutes(5)]);

    $this->artisan(AutoSubmitSesi::class)->assertSuccessful();

    $this->sesi->refresh();
    // Status harus tetap Selesai (bukan Kadaluarsa)
    expect($this->sesi->status)->toBe(StatusSesi::Selesai);
});

test('command: tidak ada sesi kadaluarsa → command tetap sukses', function () {
    // Tidak ada sesi lewat batas
    $this->artisan(AutoSubmitSesi::class)->assertSuccessful();
});

test('command: waktu_selesai diisi dengan waktu_batas (bukan now())', function () {
    $waktuBatas = Carbon::now()->subMinutes(3);
    $this->sesi->update(['waktu_batas' => $waktuBatas]);

    $this->artisan(AutoSubmitSesi::class)->assertSuccessful();

    $this->sesi->refresh();
    expect($this->sesi->status)->toBe(StatusSesi::Kadaluarsa);

    // waktu_selesai harus sama persis dengan waktu_batas
    expect($this->sesi->waktu_selesai->toDateTimeString())
        ->toBe($waktuBatas->toDateTimeString());
});
