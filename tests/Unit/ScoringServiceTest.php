<?php

use App\Enums\RoleName;
use App\Enums\StatusJadwal;
use App\Enums\StatusSesi;
use App\Enums\TipeSoal;
use App\Models\JadwalSoal;
use App\Models\JadwalUjian;
use App\Models\Jawaban;
use App\Models\OpsiSoal;
use App\Models\Role;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\User;
use App\Services\ScoringService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

// ─── Helper: buat fixture minimal (pakai create() langsung untuk menghindari factory sequence issues) ─────

function buatUser(): User
{
    $role = Role::where('nama_role', RoleName::Peserta)->first();

    return User::create([
        'role_id' => $role->id,
        'name' => 'Peserta Test '.uniqid(),
        'nik' => uniqid('nik'),
        'no_agenda' => uniqid('ag'),
        'is_active' => true,
    ]);
}

function buatAdmin(): User
{
    $role = Role::where('nama_role', RoleName::Admin)->first();

    return User::create([
        'role_id' => $role->id,
        'name' => 'Admin Test '.uniqid(),
        'email' => 'admin_'.uniqid().'@test.test',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
}

function buatJadwal(float $passingGrade = 60.0): JadwalUjian
{
    $admin = buatAdmin();

    return JadwalUjian::create([
        'kode_jadwal' => 'TEST-'.uniqid(),
        'nama_ujian' => 'Ujian Test',
        'waktu_mulai' => now()->subHour(),
        'waktu_selesai' => now()->addHour(),
        'durasi_menit' => 60,
        'acak_soal' => false,
        'acak_opsi' => false,
        'tampilkan_hasil' => true,
        'passing_grade' => $passingGrade,
        'status' => StatusJadwal::Berlangsung,
        'created_by' => $admin->id,
    ]);
}

function buatSesi(JadwalUjian $jadwal, User $user): SesiUjian
{
    return SesiUjian::create([
        'jadwal_ujian_id' => $jadwal->id,
        'user_id' => $user->id,
        'status' => StatusSesi::SedangBerlangsung,
        'waktu_mulai' => now(),
        'waktu_batas' => now()->addHour(),
        'jumlah_pelanggaran' => 0,
    ]);
}

function adminId(): int
{
    static $id = null;
    if ($id !== null) {
        // Cek apakah masih ada dalam transaksi yang sama (user masih ada di DB)
        if (User::find($id)) {
            return $id;
        }
        $id = null;
    }
    $id = buatAdmin()->id;

    return $id;
}

function buatSoalPG(JadwalUjian $jadwal, int $kunci = 0, int $nomorUrut = 1, float $poin = 10): array
{
    $soal = Soal::create(['tipe' => TipeSoal::Pg, 'pertanyaan' => 'Pertanyaan PG?', 'poin' => $poin, 'created_by' => adminId()]);
    for ($i = 0; $i < 4; $i++) {
        OpsiSoal::create(['soal_id' => $soal->id, 'teks' => "Opsi {$i}", 'is_kunci' => $i === $kunci, 'nomor_urut' => $i + 1]);
    }
    JadwalSoal::create(['jadwal_ujian_id' => $jadwal->id, 'soal_id' => $soal->id, 'nomor_urut' => $nomorUrut]);

    return [$soal, $soal->opsi()->where('is_kunci', true)->first()];
}

function buatSoalBS(JadwalUjian $jadwal, bool $jawaban = true, int $nomorUrut = 1, float $poin = 10): Soal
{
    $soal = Soal::create(['tipe' => TipeSoal::BenarSalah, 'pertanyaan' => 'Pernyataan ini benar?', 'jawaban_benar_bool' => $jawaban, 'poin' => $poin, 'created_by' => adminId()]);
    JadwalSoal::create(['jadwal_ujian_id' => $jadwal->id, 'soal_id' => $soal->id, 'nomor_urut' => $nomorUrut]);

    return $soal;
}

function buatSoalLabeling(JadwalUjian $jadwal, int $jumlahLabel = 3, int $nomorUrut = 1, float $poin = 10): array
{
    $soal = Soal::create(['tipe' => TipeSoal::Labeling, 'pertanyaan' => 'Beri label bagian ini!', 'poin' => $poin, 'created_by' => adminId()]);
    $opsiList = [];
    for ($i = 1; $i <= $jumlahLabel; $i++) {
        $opsiList[] = OpsiSoal::create(['soal_id' => $soal->id, 'teks' => "Label {$i}", 'nomor_urut' => $i]);
    }
    JadwalSoal::create(['jadwal_ujian_id' => $jadwal->id, 'soal_id' => $soal->id, 'nomor_urut' => $nomorUrut]);

    return [$soal, $opsiList];
}

function buatSoalMenjodohkan(JadwalUjian $jadwal, int $jumlahPasangan = 3, int $nomorUrut = 1, float $poin = 10): array
{
    $soal = Soal::create(['tipe' => TipeSoal::Menjodohkan, 'pertanyaan' => 'Jodohkan item berikut!', 'poin' => $poin, 'created_by' => adminId()]);
    $opsiList = [];
    for ($i = 1; $i <= $jumlahPasangan; $i++) {
        $opsiList[] = OpsiSoal::create(['soal_id' => $soal->id, 'teks' => "Item {$i}", 'pasangan' => "Pasangan {$i}", 'nomor_urut' => $i]);
    }
    JadwalSoal::create(['jadwal_ujian_id' => $jadwal->id, 'soal_id' => $soal->id, 'nomor_urut' => $nomorUrut]);

    return [$soal, $opsiList];
}

// ─── PG ─────────────────────────────────────────────────────────────────────

test('ScoringService: PG semua benar → skor_pg=100', function () {
    $jadwal = buatJadwal();
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    [$soal1, $kunci1] = buatSoalPG($jadwal, kunci: 0, nomorUrut: 1, poin: 10);
    [$soal2, $kunci2] = buatSoalPG($jadwal, kunci: 2, nomorUrut: 2, poin: 10);

    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal1->id, 'opsi_id' => $kunci1->id, 'waktu_jawab' => now()]);
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal2->id, 'opsi_id' => $kunci2->id, 'waktu_jawab' => now()]);

    $result = app(ScoringService::class)->score($sesi);

    expect($result['skor_pg'])->toBe(100.0);
    expect($result['skor_total'])->toBe(100.0);
    expect($result['is_lulus'])->toBeTrue();
});

test('ScoringService: PG semua salah → skor_pg=0', function () {
    $jadwal = buatJadwal();
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    [$soal, $kunci] = buatSoalPG($jadwal, kunci: 0, poin: 10);
    $opsiSalah = $soal->opsi()->where('is_kunci', false)->first();

    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'opsi_id' => $opsiSalah->id, 'waktu_jawab' => now()]);

    $result = app(ScoringService::class)->score($sesi);

    expect($result['skor_pg'])->toBe(0.0);
    expect($result['is_lulus'])->toBeFalse();
});

test('ScoringService: PG parsial 1 dari 3 benar → skor_pg≈33.33', function () {
    $jadwal = buatJadwal();
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    [$soal1, $kunci1] = buatSoalPG($jadwal, kunci: 0, nomorUrut: 1);
    [$soal2, $kunci2] = buatSoalPG($jadwal, kunci: 1, nomorUrut: 2);
    [$soal3, $kunci3] = buatSoalPG($jadwal, kunci: 2, nomorUrut: 3);

    $opsiSalah2 = $soal2->opsi()->where('is_kunci', false)->first();
    $opsiSalah3 = $soal3->opsi()->where('is_kunci', false)->first();

    // soal1 benar, soal2 & soal3 salah
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal1->id, 'opsi_id' => $kunci1->id, 'waktu_jawab' => now()]);
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal2->id, 'opsi_id' => $opsiSalah2->id, 'waktu_jawab' => now()]);
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal3->id, 'opsi_id' => $opsiSalah3->id, 'waktu_jawab' => now()]);

    $result = app(ScoringService::class)->score($sesi);

    expect($result['skor_pg'])->toBe(round(1 / 3 * 100, 4));
});

// ─── Benar-Salah ────────────────────────────────────────────────────────────

test('ScoringService: BenarSalah jawaban benar → skor_benar_salah=100', function () {
    $jadwal = buatJadwal();
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    $soal = buatSoalBS($jadwal, jawaban: true);

    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'jawaban_bool' => true, 'waktu_jawab' => now()]);

    $result = app(ScoringService::class)->score($sesi);

    expect($result['skor_benar_salah'])->toBe(100.0);
});

test('ScoringService: BenarSalah jawaban salah → skor_benar_salah=0', function () {
    $jadwal = buatJadwal();
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    $soal = buatSoalBS($jadwal, jawaban: true);

    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'jawaban_bool' => false, 'waktu_jawab' => now()]);

    $result = app(ScoringService::class)->score($sesi);

    expect($result['skor_benar_salah'])->toBe(0.0);
});

// ─── Labeling ───────────────────────────────────────────────────────────────

test('ScoringService: Labeling semua label cocok → skor_labeling=100', function () {
    $jadwal = buatJadwal();
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    [$soal, $opsiList] = buatSoalLabeling($jadwal, jumlahLabel: 3);

    // Isi semua label dengan nomor_jawaban == nomor_urut (benar)
    foreach ($opsiList as $opsi) {
        Jawaban::create([
            'sesi_ujian_id' => $sesi->id,
            'soal_id' => $soal->id,
            'opsi_id' => $opsi->id,
            'nomor_jawaban' => $opsi->nomor_urut,
            'waktu_jawab' => now(),
        ]);
    }

    $result = app(ScoringService::class)->score($sesi);

    expect($result['skor_labeling'])->toBe(100.0);
});

test('ScoringService: Labeling 2 dari 3 benar → skor_labeling=0 (all-or-nothing)', function () {
    $jadwal = buatJadwal();
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    [$soal, $opsiList] = buatSoalLabeling($jadwal, jumlahLabel: 3);

    // Dua opsi benar, satu salah (nomor_jawaban tidak cocok)
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'opsi_id' => $opsiList[0]->id, 'nomor_jawaban' => 1, 'waktu_jawab' => now()]);
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'opsi_id' => $opsiList[1]->id, 'nomor_jawaban' => 2, 'waktu_jawab' => now()]);
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'opsi_id' => $opsiList[2]->id, 'nomor_jawaban' => 99, 'waktu_jawab' => now()]); // salah

    $result = app(ScoringService::class)->score($sesi);

    expect($result['skor_labeling'])->toBe(0.0);
    expect($result['jawaban_updates'][$soal->id]['is_benar'])->toBeFalse();
});

// ─── Menjodohkan ────────────────────────────────────────────────────────────

test('ScoringService: Menjodohkan semua pasangan benar → skor_menjodohkan=100', function () {
    $jadwal = buatJadwal();
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    [$soal, $opsiList] = buatSoalMenjodohkan($jadwal, jumlahPasangan: 3);

    // Benar: pasangan_opsi_id == opsi->id untuk setiap opsi
    foreach ($opsiList as $opsi) {
        Jawaban::create([
            'sesi_ujian_id' => $sesi->id,
            'soal_id' => $soal->id,
            'opsi_id' => $opsi->id,
            'pasangan_opsi_id' => $opsi->id,
            'waktu_jawab' => now(),
        ]);
    }

    $result = app(ScoringService::class)->score($sesi);

    expect($result['skor_menjodohkan'])->toBe(100.0);
    expect($result['jawaban_updates'][$soal->id]['is_benar'])->toBeTrue();
});

test('ScoringService: Menjodohkan 2 dari 3 pasangan benar → skor_menjodohkan=0 (all-or-nothing)', function () {
    $jadwal = buatJadwal();
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    [$soal, $opsiList] = buatSoalMenjodohkan($jadwal, jumlahPasangan: 3);

    // 2 benar, 1 salah
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'opsi_id' => $opsiList[0]->id, 'pasangan_opsi_id' => $opsiList[0]->id, 'waktu_jawab' => now()]);
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'opsi_id' => $opsiList[1]->id, 'pasangan_opsi_id' => $opsiList[1]->id, 'waktu_jawab' => now()]);
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'opsi_id' => $opsiList[2]->id, 'pasangan_opsi_id' => $opsiList[0]->id, 'waktu_jawab' => now()]); // salah

    $result = app(ScoringService::class)->score($sesi);

    expect($result['skor_menjodohkan'])->toBe(0.0);
});

// ─── Skor total & is_lulus ───────────────────────────────────────────────────

test('ScoringService: skor_total = rata-rata tipe yang ada (skip tipe tidak ada)', function () {
    // Jadwal hanya berisi PG + BenarSalah — labeling & menjodohkan tidak ada
    $jadwal = buatJadwal(passingGrade: 60.0);
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    // PG: 1 soal, benar → skor_pg = 100
    [$soalPg, $kunciPg] = buatSoalPG($jadwal, kunci: 0, nomorUrut: 1);
    // BenarSalah: 1 soal, salah → skor_bs = 0
    $soalBs = buatSoalBS($jadwal, jawaban: true, nomorUrut: 2);

    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soalPg->id, 'opsi_id' => $kunciPg->id, 'waktu_jawab' => now()]);
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soalBs->id, 'jawaban_bool' => false, 'waktu_jawab' => now()]);

    $result = app(ScoringService::class)->score($sesi);

    // skor_total = avg(100, 0) = 50 (bukan avg(100, 0, null, null))
    expect($result['skor_pg'])->toBe(100.0);
    expect($result['skor_benar_salah'])->toBe(0.0);
    expect($result['skor_labeling'])->toBeNull();
    expect($result['skor_menjodohkan'])->toBeNull();
    expect($result['skor_total'])->toBe(50.0);
    expect($result['is_lulus'])->toBeFalse(); // 50 < 60
});

test('ScoringService: is_lulus tepat di passing_grade', function () {
    $jadwal = buatJadwal(passingGrade: 100.0);
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    [$soal, $kunci] = buatSoalPG($jadwal, kunci: 0);
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'opsi_id' => $kunci->id, 'waktu_jawab' => now()]);

    $result = app(ScoringService::class)->score($sesi);

    expect($result['skor_total'])->toBe(100.0);
    expect($result['is_lulus'])->toBeTrue(); // 100 >= 100
});

test('ScoringService: is_lulus false saat skor_total < passing_grade', function () {
    $jadwal = buatJadwal(passingGrade: 60.0);
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    [$soal, $kunci] = buatSoalPG($jadwal, kunci: 0);
    $opsiSalah = $soal->opsi()->where('is_kunci', false)->first();
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'opsi_id' => $opsiSalah->id, 'waktu_jawab' => now()]);

    $result = app(ScoringService::class)->score($sesi);

    expect($result['skor_total'])->toBe(0.0);
    expect($result['is_lulus'])->toBeFalse();
});

// ─── Denormalisasi jawaban_updates ──────────────────────────────────────────

test('ScoringService: jawaban_updates berisi is_benar & poin_didapat per soal', function () {
    $jadwal = buatJadwal();
    $user = buatUser();
    $sesi = buatSesi($jadwal, $user);

    [$soalBenar, $kunci] = buatSoalPG($jadwal, kunci: 0, nomorUrut: 1, poin: 15);
    [$soalSalah, $kunciSalah] = buatSoalPG($jadwal, kunci: 1, nomorUrut: 2, poin: 10);
    $opsiSalah = $soalSalah->opsi()->where('is_kunci', false)->first();

    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soalBenar->id, 'opsi_id' => $kunci->id, 'waktu_jawab' => now()]);
    Jawaban::create(['sesi_ujian_id' => $sesi->id, 'soal_id' => $soalSalah->id, 'opsi_id' => $opsiSalah->id, 'waktu_jawab' => now()]);

    $result = app(ScoringService::class)->score($sesi);

    expect($result['jawaban_updates'][$soalBenar->id]['is_benar'])->toBeTrue();
    expect($result['jawaban_updates'][$soalBenar->id]['poin_didapat'])->toBe(15.0);
    expect($result['jawaban_updates'][$soalSalah->id]['is_benar'])->toBeFalse();
    expect($result['jawaban_updates'][$soalSalah->id]['poin_didapat'])->toBe(0.0);
});
