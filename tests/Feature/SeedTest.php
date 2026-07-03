<?php

use App\Enums\StatusJadwal;
use App\Enums\StatusSesi;
use App\Models\JadwalPeserta;
use App\Models\JadwalSoal;
use App\Models\JadwalUjian;
use App\Models\Role;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('seed menghasilkan 3 role', function () {
    expect(Role::count())->toBe(3);
});

test('seed menghasilkan 33 user (1 admin + 2 pengawas + 30 peserta)', function () {
    expect(User::count())->toBe(33);

    $adminId    = Role::where('nama_role', 'admin')->value('id');
    $pengawasId = Role::where('nama_role', 'pengawas')->value('id');
    $pesertaId  = Role::where('nama_role', 'peserta')->value('id');

    expect(User::where('role_id', $adminId)->count())->toBe(1);
    expect(User::where('role_id', $pengawasId)->count())->toBe(2);
    expect(User::where('role_id', $pesertaId)->count())->toBe(30);
});

test('admin bisa login dengan email dan password', function () {
    $admin = User::where('email', 'admin@cbt.test')->first();
    expect($admin)->not->toBeNull();
    expect(password_verify('password', $admin->password))->toBeTrue();
});

test('peserta login dengan NIK dan no_agenda', function () {
    $peserta = User::where('nik', '3201010101010001')->first();
    expect($peserta)->not->toBeNull();
    expect($peserta->no_agenda)->toBe('A001');
    expect($peserta->password)->toBeNull();
});

test('seed menghasilkan 50 soal: 20 PG + 10 BS + 10 Labeling + 10 Menjodohkan', function () {
    expect(Soal::count())->toBe(50);
    expect(Soal::where('tipe', 'pg')->count())->toBe(20);
    expect(Soal::where('tipe', 'benar_salah')->count())->toBe(10);
    expect(Soal::where('tipe', 'labeling')->count())->toBe(10);
    expect(Soal::where('tipe', 'menjodohkan')->count())->toBe(10);
});

test('soal PG punya 4 opsi, tepat 1 kunci', function () {
    $soal = Soal::where('tipe', 'pg')->first();
    expect($soal->opsi)->toHaveCount(4);
    expect($soal->opsi->where('is_kunci', true))->toHaveCount(1);
});

test('soal benar_salah tidak punya opsi, jawaban_benar_bool terisi', function () {
    $soal = Soal::where('tipe', 'benar_salah')->first();
    expect($soal->opsi)->toBeEmpty();
    expect($soal->jawaban_benar_bool)->not->toBeNull();
});

test('soal labeling punya media_url dan label bernomor', function () {
    $soal = Soal::where('tipe', 'labeling')->first();
    expect($soal->media_url)->not->toBeNull();
    expect($soal->opsi)->not->toBeEmpty();
    $soal->opsi->each(function ($opsi) {
        expect($opsi->nomor_urut)->toBeGreaterThan(0);
    });
});

test('soal menjodohkan punya teks + pasangan', function () {
    $soal = Soal::where('tipe', 'menjodohkan')->first();
    expect($soal->opsi)->not->toBeEmpty();
    // Semua opsi (kiri + kanan) punya teks
    $soal->opsi->each(fn ($opsi) => expect($opsi->teks)->not->toBeEmpty());
    // Opsi kiri punya pasangan; opsi kanan tidak (pasangan=null adalah valid)
    expect($soal->opsi->whereNotNull('pasangan'))->not->toBeEmpty();
});

test('seed menghasilkan 5 jadwal dengan beragam status', function () {
    expect(JadwalUjian::count())->toBe(5);
    expect(JadwalUjian::where('status', StatusJadwal::Berlangsung)->count())->toBe(1);
    expect(JadwalUjian::where('status', StatusJadwal::Selesai)->count())->toBe(1);
    expect(JadwalUjian::where('status', StatusJadwal::Dibatalkan)->count())->toBe(1);
    expect(JadwalUjian::where('status', StatusJadwal::Terbuka)->count())->toBe(1);
    expect(JadwalUjian::where('status', StatusJadwal::Draft)->count())->toBe(1);
});

test('65 soal di-attach ke 5 jadwal (MTK=14, TIK=25, BATAL=5, BIND=11, DRAFT=10)', function () {
    expect(JadwalSoal::count())->toBe(65);
    $jadwalMTK = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->firstOrFail();
    expect($jadwalMTK->jadwalSoal)->toHaveCount(14);
    $jadwalTIK = JadwalUjian::where('kode_jadwal', 'UAS-2026-TIK')->firstOrFail();
    expect($jadwalTIK->jadwalSoal)->toHaveCount(25);
});

test('100 peserta di-assign ke 4 jadwal, token 64 char (DRAFT tidak ada peserta)', function () {
    expect(JadwalPeserta::count())->toBe(100);
    JadwalPeserta::each(function ($jp) {
        expect(strlen($jp->token_akses))->toBe(64);
    });
    $jadwalDraft = JadwalUjian::where('kode_jadwal', 'UAS-DRAFT-001')->firstOrFail();
    expect(JadwalPeserta::where('jadwal_ujian_id', $jadwalDraft->id)->count())->toBe(0);
});

test('100 sesi dengan beragam status setelah simulasi', function () {
    expect(SesiUjian::count())->toBe(100);

    // TIK: semua 30 selesai (termasuk A027 dengan pelanggaran, A028-A029 skor rendah)
    $jadwalTIK = JadwalUjian::where('kode_jadwal', 'UAS-2026-TIK')->firstOrFail();
    expect(SesiUjian::where('jadwal_ujian_id', $jadwalTIK->id)
        ->where('status', StatusSesi::Selesai)->count())->toBe(30);

    // BIND: semua 30 belum_mulai
    $jadwalBIND = JadwalUjian::where('kode_jadwal', 'UAS-2026-BIND')->firstOrFail();
    expect(SesiUjian::where('jadwal_ujian_id', $jadwalBIND->id)
        ->where('status', StatusSesi::BelumMulai)->count())->toBe(30);

    // MTK: beragam status (sedang_berlangsung, selesai, belum_mulai, dibatalkan, kadaluarsa)
    $jadwalMTK = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->firstOrFail();
    expect(SesiUjian::where('jadwal_ujian_id', $jadwalMTK->id)
        ->whereIn('status', ['sedang_berlangsung', 'selesai', 'belum_mulai', 'dibatalkan', 'kadaluarsa'])
        ->count())->toBe(30);
});
