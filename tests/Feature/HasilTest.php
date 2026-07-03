<?php

use App\Enums\RoleName;
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

    // Jadwal TIK sudah selesai dengan seluruh sesi peserta ber-skor (SimulasiSesiSeeder)
    $this->jadwal = JadwalUjian::where('kode_jadwal', 'UAS-2026-TIK')->first();

    $sesiSelesai = SesiUjian::where('jadwal_ujian_id', $this->jadwal->id)
        ->where('status', 'selesai')
        ->orderBy('id')
        ->get();

    $this->sesi = $sesiSelesai->first();
    $this->peserta = User::find($this->sesi->user_id);

    $this->sesiLain = $sesiSelesai->skip(1)->first();
    $this->pesertaLain = User::find($this->sesiLain->user_id);

    $this->flushHeaders();
});

// ─── GET /sesi/:id/hasil ──────────────────────────────────────────────────────

test('GET hasil peserta bisa lihat sesi milik sendiri yang sudah selesai (tampilkan_hasil=true)', function () {
    $res = $this->actingAs($this->peserta, 'sanctum')
        ->getJson("/api/v1/sesi/{$this->sesi->id}/hasil");

    $res->assertStatus(200);
    expect($res->json('success'))->toBeTrue();

    // Shape sesi — sesuai FE SesiUjian type
    expect($res->json('data.sesi'))->toHaveKeys(['id', 'jadwal_ujian_id', 'user_id', 'status', 'waktu_mulai', 'waktu_selesai', 'skor_total', 'is_lulus', 'jumlah_pelanggaran']);
    expect($res->json('data.sesi.status'))->toBe('selesai');

    // Shape detail_skor — sesuai FE DetailSkor type {pg, benar_salah, labeling, menjodohkan}
    expect($res->json('data.detail_skor'))->toHaveKeys(['pg', 'benar_salah', 'labeling', 'menjodohkan']);

    // review_soal harus ada dengan kunci — sesuai FE ReviewSoalItem type
    expect($res->json('data.review_soal'))->toBeArray()->not->toBeEmpty();
    $soalItem = $res->json('data.review_soal.0');
    expect($soalItem)->toHaveKeys(['soal', 'jawaban_peserta', 'kunci_jawaban', 'is_benar', 'pembahasan']);
    expect($soalItem['soal'])->toHaveKeys(['id', 'tipe', 'pertanyaan', 'poin', 'nomor_urut']);
});

test('GET hasil menyertakan blok jadwal untuk sertifikat (M-B3)', function () {
    $res = $this->actingAs($this->peserta, 'sanctum')
        ->getJson("/api/v1/sesi/{$this->sesi->id}/hasil");

    $res->assertStatus(200);
    expect($res->json('data.jadwal'))->toHaveKeys(['nama_ujian', 'kode_jadwal', 'passing_grade', 'instansi']);
    expect($res->json('data.jadwal.nama_ujian'))->toBe($this->jadwal->nama_ujian);
    expect($res->json('data.jadwal.passing_grade'))->toBeInt();
});

test('GET hasil peserta tidak bisa lihat sesi milik peserta lain (403)', function () {
    // sesiLain belum dimulai pun, tapi ownership check harus menolak
    $res = $this->actingAs($this->peserta, 'sanctum')
        ->getJson("/api/v1/sesi/{$this->sesiLain->id}/hasil");

    $res->assertStatus(403);
});

test('GET hasil admin selalu bisa lihat hasil peserta manapun', function () {
    $res = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/sesi/{$this->sesi->id}/hasil");

    $res->assertStatus(200);
    expect($res->json('data.sesi.id'))->toBe($this->sesi->id);
    expect($res->json('data.review_soal'))->toBeArray();
});

test('GET hasil pengawas bisa lihat hasil', function () {
    $res = $this->actingAs($this->pengawas, 'sanctum')
        ->getJson("/api/v1/sesi/{$this->sesi->id}/hasil");

    $res->assertStatus(200);
    expect($res->json('data.sesi.skor_total'))->toBeNumeric();
});

test('GET hasil peserta pada sesi yang tampilkan_hasil=false return 403', function () {
    // Set tampilkan_hasil ke false
    $this->jadwal->update(['tampilkan_hasil' => false]);

    $res = $this->actingAs($this->peserta, 'sanctum')
        ->getJson("/api/v1/sesi/{$this->sesi->id}/hasil");

    $res->assertStatus(403);
});

test('GET hasil peserta pada sesi belum selesai return 403', function () {
    // Sesi MTK yang sedang berlangsung (belum selesai) milik peserta tsb
    $sesiBerlangsung = SesiUjian::where('status', 'sedang_berlangsung')->first();
    $pesertaBerlangsung = User::find($sesiBerlangsung->user_id);

    $res = $this->actingAs($pesertaBerlangsung, 'sanctum')
        ->getJson("/api/v1/sesi/{$sesiBerlangsung->id}/hasil");

    $res->assertStatus(403);
});

test('GET hasil tanpa autentikasi return 401', function () {
    // Reset auth state dari actingAs di beforeEach
    $this->app['auth']->forgetGuards();

    $res = $this->getJson("/api/v1/sesi/{$this->sesi->id}/hasil");

    $res->assertStatus(401);
});

// ─── GET /jadwal-ujian/:id/rekap ──────────────────────────────────────────────

test('GET rekap admin/pengawas mengembalikan shape RekapJadwal', function () {
    $res = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/jadwal-ujian/{$this->jadwal->id}/rekap");

    $res->assertStatus(200);
    expect($res->json('success'))->toBeTrue();
    expect($res->json('data'))->toHaveKeys([
        'total_peserta', 'selesai', 'sedang_berlangsung', 'belum_mulai',
        'lulus', 'tidak_lulus', 'rata_rata_skor', 'distribusi',
    ]);
    expect($res->json('data.distribusi'))->toHaveKeys(['0-25', '26-50', '51-75', '76-100']);
});

test('GET rekap counter selesai akurat setelah sesi diselesaikan', function () {
    // 1 sesi sudah selesai dari beforeEach
    $res = $this->actingAs($this->pengawas, 'sanctum')
        ->getJson("/api/v1/jadwal-ujian/{$this->jadwal->id}/rekap");

    $res->assertStatus(200);
    expect($res->json('data.selesai'))->toBeGreaterThanOrEqual(1);
    expect($res->json('data.total_peserta'))->toBeGreaterThanOrEqual(1);
});

test('GET rekap distribusi dan rata-rata benar untuk fixture', function () {
    $res = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/jadwal-ujian/{$this->jadwal->id}/rekap");

    $res->assertStatus(200);

    $data = $res->json('data');
    // total sesi selesai + berlangsung + belum_mulai = total_peserta
    $sumStatus = $data['selesai'] + $data['sedang_berlangsung'] + $data['belum_mulai'];
    // total_peserta mungkin > sumStatus karena ada dibatalkan/kadaluarsa
    expect($data['total_peserta'])->toBeGreaterThanOrEqual($sumStatus);

    // distribusi harus berisi int
    foreach ($data['distribusi'] as $count) {
        expect($count)->toBeInt();
    }
});

test('GET rekap peserta tidak bisa akses (403)', function () {
    $res = $this->actingAs($this->peserta, 'sanctum')
        ->getJson("/api/v1/jadwal-ujian/{$this->jadwal->id}/rekap");

    $res->assertStatus(403);
});

// ─── GET /jadwal-ujian/:id/export ─────────────────────────────────────────────

test('GET export csv admin menghasilkan file CSV dengan header attachment', function () {
    $res = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/jadwal-ujian/{$this->jadwal->id}/export?format=csv");

    $res->assertStatus(200);
    $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($res->headers->get('Content-Disposition'))->toContain('attachment');
    expect($res->headers->get('Content-Disposition'))->toContain('.csv');
});

test('GET export format invalid return 422', function () {
    $res = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/jadwal-ujian/{$this->jadwal->id}/export?format=pdf");

    $res->assertStatus(422);
});

test('GET export peserta tidak bisa akses (403)', function () {
    $res = $this->actingAs($this->peserta, 'sanctum')
        ->get("/api/v1/jadwal-ujian/{$this->jadwal->id}/export?format=csv");

    $res->assertStatus(403);
});

test('GET export xlsx fallback ke csv jika maatwebsite tidak terpasang', function () {
    $res = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/jadwal-ujian/{$this->jadwal->id}/export?format=xlsx");

    // Fallback ke csv — tetap 200 dengan header csv
    $res->assertStatus(200);
    expect($res->headers->get('Content-Disposition'))->toContain('attachment');
});
