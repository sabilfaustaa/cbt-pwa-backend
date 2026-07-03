<?php

use App\Enums\TipeSoal;
use App\Models\JadwalPeserta;
use App\Models\JadwalSoal;
use App\Models\JadwalUjian;
use App\Models\Jawaban;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@cbt.test')->first();
    $this->peserta = User::where('role_id', 3)->first();

    // Pin ke jadwal MTK (berlangsung) — `first()` tanpa orderBy di PostgreSQL
    // bisa me-resolve sesi jadwal lain yang belum_mulai (akar F-05).
    $jadwalMtkId = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->value('id');
    $this->sesi = SesiUjian::where('user_id', $this->peserta->id)
        ->where('jadwal_ujian_id', $jadwalMtkId)
        ->firstOrFail();

    $tokenAkses = JadwalPeserta::where('user_id', $this->peserta->id)
        ->where('jadwal_ujian_id', $jadwalMtkId)
        ->value('token_akses');
    $pesertaToken = $this->peserta->createToken('test')->plainTextToken;

    $this->withToken($pesertaToken)->postJson('/api/v1/sesi/mulai', [
        'token_akses' => $tokenAkses,
    ])->assertStatus(200);

    $this->sesi->refresh();
    $this->flushHeaders();
});

function pesertaToken(): string
{
    $peserta = User::where('role_id', 3)->first();

    return $peserta->createToken('test')->plainTextToken;
}

/**
 * Ambil soal bertipe tertentu yang benar-benar terdaftar di jadwal MTK —
 * `Soal::first()` global bisa mengembalikan soal TIK yang bukan bagian
 * jadwal sesi fixture (422 "Soal ini bukan bagian dari jadwal ujian").
 */
function soalMtk(TipeSoal $tipe): Soal
{
    $jadwalId = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->value('id');
    $soalIds = JadwalSoal::where('jadwal_ujian_id', $jadwalId)->pluck('soal_id');

    return Soal::where('tipe', $tipe)->whereIn('id', $soalIds)->orderBy('id')->firstOrFail();
}

// ─── PUT /sesi/:id/jawaban (PG) ────────────────────────────────

test('PUT jawaban PG simpan & return 200', function () {
    $soalPg = soalMtk(TipeSoal::Pg);
    $opsi = $soalPg->opsi()->first();

    $res = $this->withToken(pesertaToken())->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalPg->id,
        'opsi_id' => $opsi->id,
    ]);

    $res->assertStatus(200);
    $res->assertJsonPath('data.jawaban.soal_id', $soalPg->id);
    $res->assertJsonPath('data.jawaban.opsi_id', $opsi->id);
    expect($res->json('data.jawaban.jawaban_bool'))->toBeNull();
    expect($res->json('data.sisa_detik'))->toBeInt();
    expect($res->json('data.server_time'))->toBeString();

    $this->assertDatabaseHas('jawaban', [
        'sesi_ujian_id' => $this->sesi->id,
        'soal_id' => $soalPg->id,
        'opsi_id' => $opsi->id,
    ]);
});

test('PUT jawaban PG update jawaban mengganti → satu baris, jawaban terakhir', function () {
    // F-03: ganti jawaban PG harus meng-UPDATE baris yang sama (bukan menambah
    // baris duplikat yang membuat scoring menilai jawaban lama).
    $soalPg = soalMtk(TipeSoal::Pg);
    $opsi1 = $soalPg->opsi()->first();
    $opsi2 = $soalPg->opsi()->skip(1)->first();
    $token = pesertaToken();

    $this->withToken($token)->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalPg->id,
        'opsi_id' => $opsi1->id,
    ]);

    $res = $this->withToken($token)->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalPg->id,
        'opsi_id' => $opsi2->id,
    ]);

    $res->assertStatus(200);
    expect($res->json('data.jawaban.opsi_id'))->toBe($opsi2->id);

    $rows = Jawaban::where('sesi_ujian_id', $this->sesi->id)
        ->where('soal_id', $soalPg->id)
        ->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->opsi_id)->toBe($opsi2->id);
});

test('F-03: skor dinilai dari jawaban PG terakhir setelah diganti', function () {
    // Jawab SALAH dulu, lalu ganti ke kunci — skor & review harus menilai kunci.
    $soalPg = soalMtk(TipeSoal::Pg);
    $kunci = $soalPg->opsi()->where('is_kunci', true)->first();
    $salah = $soalPg->opsi()->where('is_kunci', false)->first();
    $token = pesertaToken();

    $this->withToken($token)->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalPg->id,
        'opsi_id' => $salah->id,
    ])->assertStatus(200);

    $this->withToken($token)->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalPg->id,
        'opsi_id' => $kunci->id,
    ])->assertStatus(200);

    $this->withToken($token)->postJson("/api/v1/sesi/{$this->sesi->id}/selesai")
        ->assertStatus(200);

    $jawaban = Jawaban::where('sesi_ujian_id', $this->sesi->id)
        ->where('soal_id', $soalPg->id)
        ->first();
    expect($jawaban->opsi_id)->toBe($kunci->id);
    expect($jawaban->is_benar)->toBeTrue();
});

// ─── PUT /sesi/:id/jawaban (Benar-Salah) ──────────────────────

test('PUT jawaban benar_salah simpan & return 200', function () {
    $soalBs = soalMtk(TipeSoal::BenarSalah);

    $res = $this->withToken(pesertaToken())->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalBs->id,
        'jawaban_bool' => true,
    ]);

    $res->assertStatus(200);
    expect($res->json('data.jawaban.jawaban_bool'))->toBeTrue();
    expect($res->json('data.jawaban.opsi_id'))->toBeNull();

    $this->assertDatabaseHas('jawaban', [
        'sesi_ujian_id' => $this->sesi->id,
        'soal_id' => $soalBs->id,
        'jawaban_bool' => true,
    ]);
});

test('PUT jawaban benar_salah update jawaban → tidak duplikat', function () {
    $soalBs = soalMtk(TipeSoal::BenarSalah);
    $token = pesertaToken();

    $this->withToken($token)->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalBs->id,
        'jawaban_bool' => true,
    ]);

    $res = $this->withToken($token)->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalBs->id,
        'jawaban_bool' => false,
    ]);

    $res->assertStatus(200);
    expect($res->json('data.jawaban.jawaban_bool'))->toBeFalse();

    $rows = Jawaban::where('sesi_ujian_id', $this->sesi->id)
        ->where('soal_id', $soalBs->id)
        ->count();
    expect($rows)->toBe(1);
});

// ─── PUT /sesi/:id/jawaban (Labeling) ──────────────────────────

test('PUT jawaban labeling simpan satu label', function () {
    $soalLabel = soalMtk(TipeSoal::Labeling);
    $opsi = $soalLabel->opsi()->first();

    $res = $this->withToken(pesertaToken())->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalLabel->id,
        'opsi_id' => $opsi->id,
        'nomor_jawaban' => 1,
    ]);

    $res->assertStatus(200);
    expect($res->json('data.jawaban.opsi_id'))->toBe($opsi->id);
    expect($res->json('data.jawaban.nomor_jawaban'))->toBe(1);
});

// ─── PUT /sesi/:id/jawaban (Menjodohkan) ───────────────────────

test('PUT jawaban menjodohkan simpan pasangan', function () {
    $soalJodoh = soalMtk(TipeSoal::Menjodohkan);
    $opsi = $soalJodoh->opsi()->first();

    $res = $this->withToken(pesertaToken())->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalJodoh->id,
        'opsi_id' => $opsi->id,
        'pasangan_opsi_id' => $opsi->id,
    ]);

    $res->assertStatus(200);
    expect($res->json('data.jawaban.pasangan_opsi_id'))->toBe($opsi->id);
});

// ─── Waktu habis → 409 ─────────────────────────────────────────

test('PUT jawaban setelah waktu_batas returns 409', function () {
    $soalPg = soalMtk(TipeSoal::Pg);
    $opsi = $soalPg->opsi()->first();

    $this->sesi->update(['waktu_batas' => now()->subMinute()]);

    $res = $this->withToken(pesertaToken())->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalPg->id,
        'opsi_id' => $opsi->id,
    ]);

    $res->assertStatus(409);
    expect($res->json('meta.message'))->toBe('Waktu ujian sudah habis.');
});

// ─── Validasi field per tipe ───────────────────────────────────

test('PUT jawaban PG tanpa opsi_id returns error', function () {
    $soalPg = soalMtk(TipeSoal::Pg);

    $res = $this->withToken(pesertaToken())->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalPg->id,
    ]);

    $res->assertStatus(422);
    expect($res->json('meta.errors.opsi_id'))->not->toBeNull();
});

test('PUT jawaban benar_salah dengan opsi_id returns error', function () {
    $soalBs = soalMtk(TipeSoal::BenarSalah);

    $res = $this->withToken(pesertaToken())->putJson("/api/v1/sesi/{$this->sesi->id}/jawaban", [
        'soal_id' => $soalBs->id,
        'jawaban_bool' => true,
        'opsi_id' => 1,
    ]);

    $res->assertStatus(422);
});

// ─── GET /sesi/:id/heartbeat ───────────────────────────────────

test('GET heartbeat returns normal state when belum kadaluarsa', function () {
    $res = $this->withToken(pesertaToken())->getJson("/api/v1/sesi/{$this->sesi->id}/heartbeat");

    $res->assertStatus(200);
    expect($res->json('data.status'))->toBe('sedang_berlangsung');
    expect($res->json('data.sisa_detik'))->toBeInt()->toBeGreaterThan(0);
    expect($res->json('data.server_time'))->toBeString();
    expect($res->json('data.waktu_batas'))->toBeString();
});

test('GET heartbeat auto-submit kadaluarsa when waktu_batas lewat', function () {
    $this->sesi->update(['waktu_batas' => now()->subMinute()]);

    $res = $this->withToken(pesertaToken())->getJson("/api/v1/sesi/{$this->sesi->id}/heartbeat");

    $res->assertStatus(200);
    expect($res->json('data.status'))->toBe('kadaluarsa');
    expect($res->json('data.sisa_detik'))->toBe(0);

    $this->sesi->refresh();
    expect($this->sesi->status->value)->toBe('kadaluarsa');
    expect($this->sesi->waktu_selesai)->not->toBeNull();
    // F-01: heartbeat yang menutup sesi WAJIB men-score — skor tidak boleh NULL
    expect($this->sesi->skor_total)->toBeNumeric();
});

// ─── POST /sesi/:id/aktivitas ──────────────────────────────────

test('POST aktivitas mencatat & increment pelanggaran', function () {
    $before = $this->sesi->jumlah_pelanggaran;

    $res = $this->withToken(pesertaToken())->postJson("/api/v1/sesi/{$this->sesi->id}/aktivitas", [
        'jenis' => 'tab_blur',
        'metadata' => ['durasi_ms' => 2500],
    ]);

    $res->assertStatus(201);
    expect($res->json('success'))->toBeTrue();

    $this->assertDatabaseHas('sesi_aktivitas', [
        'sesi_ujian_id' => $this->sesi->id,
        'jenis' => 'tab_blur',
    ]);

    $this->sesi->refresh();
    expect($this->sesi->jumlah_pelanggaran)->toBe($before + 1);
});

// ─── Auth checks ────────────────────────────────────────────────

test('M10 jawaban endpoints require role peserta', function () {
    $this->actingAs($this->admin, 'sanctum');
    $this->getJson('/api/v1/sesi/saya')->assertStatus(403);

    $this->getJson('/api/v1/sesi/1/heartbeat')->assertStatus(403);
    $this->postJson('/api/v1/sesi/1/aktivitas', ['jenis' => 'test'])->assertStatus(403);
});
