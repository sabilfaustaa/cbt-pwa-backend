<?php

use App\Enums\RoleName;
use App\Enums\StatusSesi;
use App\Models\AuditLog;
use App\Models\JadwalPeserta;
use App\Models\JadwalSoal;
use App\Models\JadwalUjian;
use App\Models\Role;
use App\Models\SesiUjian;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $pengawasRoleId = Role::where('nama_role', RoleName::Pengawas->value)->value('id');
    $adminRoleId = Role::where('nama_role', RoleName::Admin->value)->value('id');

    $this->admin = User::where('role_id', $adminRoleId)->first();
    $this->pengawas = User::where('role_id', $pengawasRoleId)->first();

    // Jadwal MTK sedang berlangsung & aktif — pakai peserta yang belum mulai
    // lalu mulai sesinya agar bisa diintervensi (sedang_berlangsung, 0 jawaban).
    $this->jadwal = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->first();
    $this->sesi = SesiUjian::where('jadwal_ujian_id', $this->jadwal->id)
        ->where('status', 'belum_mulai')
        ->first();
    $this->peserta = User::find($this->sesi->user_id);

    $tokenAkses = JadwalPeserta::where('jadwal_ujian_id', $this->jadwal->id)
        ->where('user_id', $this->peserta->id)
        ->value('token_akses');
    $this->actingAs($this->peserta, 'sanctum')
        ->postJson('/api/v1/sesi/mulai', ['token_akses' => $tokenAkses])
        ->assertStatus(200);

    $this->sesi->refresh();
    $this->flushHeaders();
});

// ─── GET /pengawas/jadwal/:id/monitor ─────────────────────────────────────────

test('GET monitor mengembalikan shape MonitorSesiItem yang benar', function () {
    $res = $this->actingAs($this->pengawas, 'sanctum')
        ->getJson("/api/v1/pengawas/jadwal/{$this->jadwal->id}/monitor");

    $res->assertStatus(200);
    expect($res->json('success'))->toBeTrue();

    $items = $res->json('data');
    expect($items)->toBeArray()->not->toBeEmpty();

    $item = $items[0];
    expect($item)->toHaveKeys([
        'sesi_id', 'user', 'status', 'sisa_detik',
        'waktu_mulai', 'waktu_batas', 'jumlah_dijawab', 'total_soal', 'jumlah_pelanggaran',
    ]);
    expect($item['user'])->toHaveKeys(['id', 'nama', 'nik']);
});

test('GET monitor counter total_soal akurat sesuai jadwal_soal', function () {
    $expectedTotalSoal = JadwalSoal::where('jadwal_ujian_id', $this->jadwal->id)->count();

    $res = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/pengawas/jadwal/{$this->jadwal->id}/monitor");

    $res->assertStatus(200);
    $items = $res->json('data');
    foreach ($items as $item) {
        expect($item['total_soal'])->toBe($expectedTotalSoal);
    }
});

test('GET monitor jumlah_dijawab mencerminkan jumlah soal distinct yang dijawab', function () {
    $res = $this->actingAs($this->pengawas, 'sanctum')
        ->getJson("/api/v1/pengawas/jadwal/{$this->jadwal->id}/monitor");

    $res->assertStatus(200);
    $sesiItem = collect($res->json('data'))
        ->firstWhere('sesi_id', $this->sesi->id);

    expect($sesiItem)->not->toBeNull();
    expect($sesiItem['jumlah_dijawab'])->toBe(0);
});

test('GET monitor peserta tidak bisa akses endpoint pengawas (403)', function () {
    $res = $this->actingAs($this->peserta, 'sanctum')
        ->getJson("/api/v1/pengawas/jadwal/{$this->jadwal->id}/monitor");

    $res->assertStatus(403);
});

// ─── POST /pengawas/sesi/:id/tambah-waktu ─────────────────────────────────────

test('POST tambah-waktu menggeser waktu_batas tepat N menit', function () {
    $waktuBatasAwal = $this->sesi->waktu_batas;

    $res = $this->actingAs($this->pengawas, 'sanctum')->postJson(
        "/api/v1/pengawas/sesi/{$this->sesi->id}/tambah-waktu",
        ['tambahan_menit' => 10, 'alasan' => 'Gangguan teknis.']
    );

    $res->assertStatus(200);
    expect($res->json('data.sesi.id'))->toBe($this->sesi->id);
    expect($res->json('data.sesi.waktu_batas'))->toBeString();

    $this->sesi->refresh();
    $selisih = (int) $waktuBatasAwal->diffInMinutes($this->sesi->waktu_batas);
    expect($selisih)->toBe(10);
});

test('POST tambah-waktu mencatat audit sesi.tambah_waktu', function () {
    $this->actingAs($this->pengawas, 'sanctum')->postJson(
        "/api/v1/pengawas/sesi/{$this->sesi->id}/tambah-waktu",
        ['tambahan_menit' => 5, 'alasan' => 'Test alasan.']
    )->assertStatus(200);

    $audit = AuditLog::where('action', 'sesi.tambah_waktu')
        ->where('entity_id', $this->sesi->id)
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->metadata['tambahan_menit'])->toBe(5);
    expect($audit->metadata['alasan'])->toBe('Test alasan.');
});

test('POST tambah-waktu tanpa alasan return 422', function () {
    $res = $this->actingAs($this->pengawas, 'sanctum')->postJson(
        "/api/v1/pengawas/sesi/{$this->sesi->id}/tambah-waktu",
        ['tambahan_menit' => 5]
    );

    $res->assertStatus(422);
});

test('POST tambah-waktu pada sesi belum_mulai return 409', function () {
    $sesi2 = SesiUjian::where('user_id', '!=', $this->peserta->id)
        ->where('status', StatusSesi::BelumMulai)
        ->first();

    if (! $sesi2) {
        $this->markTestSkipped('Tidak ada sesi belum_mulai tersedia.');
    }

    $res = $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/pengawas/sesi/{$sesi2->id}/tambah-waktu",
        ['tambahan_menit' => 5, 'alasan' => 'Test.']
    );

    $res->assertStatus(409);
});

test('POST tambah-waktu peserta tidak bisa akses (403)', function () {
    $res = $this->actingAs($this->peserta, 'sanctum')->postJson(
        "/api/v1/pengawas/sesi/{$this->sesi->id}/tambah-waktu",
        ['tambahan_menit' => 5, 'alasan' => 'Test.']
    );

    $res->assertStatus(403);
});

// ─── POST /pengawas/sesi/:id/batalkan ─────────────────────────────────────────

test('POST batalkan mengubah status sesi menjadi dibatalkan', function () {
    $res = $this->actingAs($this->pengawas, 'sanctum')->postJson(
        "/api/v1/pengawas/sesi/{$this->sesi->id}/batalkan",
        ['alasan' => 'Peserta melanggar aturan.']
    );

    $res->assertStatus(200);
    expect($res->json('success'))->toBeTrue();

    $this->sesi->refresh();
    expect($this->sesi->status)->toBe(StatusSesi::Dibatalkan);
    expect($this->sesi->waktu_selesai)->not->toBeNull();
});

test('POST batalkan mencatat audit sesi.batalkan', function () {
    $this->actingAs($this->admin, 'sanctum')->postJson(
        "/api/v1/pengawas/sesi/{$this->sesi->id}/batalkan",
        ['alasan' => 'Alasan pembatalan.']
    )->assertStatus(200);

    $audit = AuditLog::where('action', 'sesi.batalkan')
        ->where('entity_id', $this->sesi->id)
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->metadata['alasan'])->toBe('Alasan pembatalan.');
});

test('POST batalkan tanpa alasan return 422', function () {
    $res = $this->actingAs($this->pengawas, 'sanctum')->postJson(
        "/api/v1/pengawas/sesi/{$this->sesi->id}/batalkan",
        []
    );

    $res->assertStatus(422);
});

test('POST batalkan sesi yang sudah selesai return 409', function () {
    // Selesaikan sesi lebih dulu
    $this->actingAs($this->peserta, 'sanctum')
        ->postJson("/api/v1/sesi/{$this->sesi->id}/selesai")
        ->assertStatus(200);

    $res = $this->actingAs($this->pengawas, 'sanctum')->postJson(
        "/api/v1/pengawas/sesi/{$this->sesi->id}/batalkan",
        ['alasan' => 'Terlambat.']
    );

    $res->assertStatus(409);
});

test('POST batalkan peserta tidak bisa akses (403)', function () {
    $res = $this->actingAs($this->peserta, 'sanctum')->postJson(
        "/api/v1/pengawas/sesi/{$this->sesi->id}/batalkan",
        ['alasan' => 'Test.']
    );

    $res->assertStatus(403);
});
