<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\StatusJadwal;
use App\Models\JadwalPeserta;
use App\Models\JadwalSoal;
use App\Models\JadwalUjian;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 5 skenario jadwal ujian — semua waktu relatif terhadap now() agar reusable.
 *
 * ┌────────────────────────────────────────────────────────────────────────────────┐
 * │ ID │ Kode Jadwal    │ Mata Pelajaran   │ Status      │ Waktu                  │
 * ├────────────────────────────────────────────────────────────────────────────────┤
 * │  1 │ UAS-2026-MTK   │ Matematika       │ berlangsung │ mulai -35 mnt          │
 * │  2 │ UAS-2026-TIK   │ TIK              │ selesai     │ 2 minggu lalu          │
 * │  3 │ UAS-BATAL-001  │ (TIK, 5 soal)   │ dibatalkan  │ kemarin                │
 * │  4 │ UAS-2026-BIND  │ Bahasa Indonesia │ terbuka     │ hari ini +4 jam        │
 * │  5 │ UAS-DRAFT-001  │ (TIK, 10 soal)  │ draft       │ +7 hari                │
 * └────────────────────────────────────────────────────────────────────────────────┘
 *
 * Urutan pembuatan: MTK dulu (ID 1, sesi ID 1-30) sehingga:
 *   - JadwalTest DELETE /jadwal/1 → 409 (jadwal 1 punya sesi)
 *   - SesiUjian::where('user_id',...)->first() di AutoSubmitTest mendapat sesi MTK
 *   - JadwalPeserta::where('user_id',...)->value('token_akses') dapat token MTK
 *   - assign-peserta idempotent test [user_id 4,5] → user 4=A001, 5=A002 sudah di-assign ke jadwal 1
 *
 * Kode UAS-2026-* dipertahankan agar kompatibel dengan feature tests.
 */
class JadwalUjianSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('email', 'admin@cbt.test')->value('id');

        $allPeserta = User::whereHas('role', fn ($q) => $q->where('nama_role', RoleName::Peserta))
            ->orderBy('no_agenda')
            ->pluck('id');

        $first10Peserta = $allPeserta->take(10);

        // ── 1. Berlangsung: ujian SEDANG BERJALAN (mulai 35 menit lalu) ──────
        // ID 1 — sesi IDs 1–30 (A001 sesi = ID 1), JadwalPeserta IDs 1–30
        JadwalUjian::create([
            'kode_jadwal'     => 'UAS-2026-MTK',
            'nama_ujian'      => 'UAS Genap — Matematika Dasar',
            'deskripsi'       => 'Ujian Akhir Semester Genap 2025/2026 mata pelajaran Matematika. '
                . 'Saat ini sedang berlangsung — sisa waktu sekitar 55 menit.',
            'waktu_mulai'     => now()->subMinutes(35),
            'waktu_selesai'   => now()->addMinutes(55),
            'durasi_menit'    => 90,
            'acak_soal'       => true,
            'acak_opsi'       => false,
            'tampilkan_hasil' => true,
            'passing_grade'   => 65,
            'status'          => StatusJadwal::Berlangsung,
            'created_by'      => $adminId,
        ]);
        $jadwalBerlangsung = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->firstOrFail();
        $this->attachSoal($jadwalBerlangsung->id, SoalSeeder::$mtkIds);
        $this->assignPeserta($jadwalBerlangsung->id, $allPeserta);

        // ── 2. Selesai: ujian selesai 2 minggu lalu, skor lengkap ────────────
        JadwalUjian::create([
            'kode_jadwal'     => 'UAS-2026-TIK',
            'nama_ujian'      => 'UAS Genap — Teknologi Informasi & Komunikasi',
            'deskripsi'       => 'Ujian Akhir Semester Genap 2025/2026 mata pelajaran TIK. '
                . 'Telah selesai. Hasil, rekap, dan analitik tersedia untuk semua pihak.',
            'waktu_mulai'     => now()->subWeeks(2)->setTime(7, 30, 0),
            'waktu_selesai'   => now()->subWeeks(2)->setTime(9, 30, 0),
            'durasi_menit'    => 90,
            'acak_soal'       => true,
            'acak_opsi'       => true,
            'tampilkan_hasil' => true,
            'passing_grade'   => 70,
            'status'          => StatusJadwal::Selesai,
            'created_by'      => $adminId,
        ]);
        $jadwalSelesai = JadwalUjian::where('kode_jadwal', 'UAS-2026-TIK')->firstOrFail();
        $this->attachSoal($jadwalSelesai->id, SoalSeeder::$tikIds);
        $this->assignPeserta($jadwalSelesai->id, $allPeserta);

        // ── 3. Dibatalkan: jadwal kemarin dibatalkan sebelum ujian selesai ───
        JadwalUjian::create([
            'kode_jadwal'     => 'UAS-BATAL-001',
            'nama_ujian'      => 'UAS Genap — Seni Budaya (DIBATALKAN)',
            'deskripsi'       => 'Jadwal dibatalkan karena force majeure. '
                . 'Semua sesi peserta berstatus dibatalkan.',
            'waktu_mulai'     => now()->subDay()->setTime(10, 0, 0),
            'waktu_selesai'   => now()->subDay()->setTime(11, 30, 0),
            'durasi_menit'    => 90,
            'acak_soal'       => false,
            'acak_opsi'       => false,
            'tampilkan_hasil' => false,
            'passing_grade'   => 75,
            'status'          => StatusJadwal::Dibatalkan,
            'created_by'      => $adminId,
        ]);
        $jadwalBatal = JadwalUjian::where('kode_jadwal', 'UAS-BATAL-001')->firstOrFail();
        $this->attachSoal($jadwalBatal->id, array_slice(SoalSeeder::$tikIds, 0, 5));
        $this->assignPeserta($jadwalBatal->id, $first10Peserta);

        // ── 4. Terbuka: ujian HARI INI jam sore, peserta sudah assign ────────
        $jadwalMendatang = JadwalUjian::create([
            'kode_jadwal'     => 'UAS-2026-BIND',
            'nama_ujian'      => 'UAS Genap — Bahasa Indonesia',
            'deskripsi'       => 'Ujian Akhir Semester Genap 2025/2026 mata pelajaran Bahasa Indonesia. '
                . 'Semua peserta sudah memiliki token akses. Ujian dimulai hari ini.',
            'waktu_mulai'     => now()->addHours(4)->startOfHour(),
            'waktu_selesai'   => now()->addHours(5)->startOfHour(),
            'durasi_menit'    => 60,
            'acak_soal'       => false,
            'acak_opsi'       => false,
            'tampilkan_hasil' => true,
            'passing_grade'   => 75,
            'status'          => StatusJadwal::Terbuka,
            'created_by'      => $adminId,
        ]);
        $this->attachSoal($jadwalMendatang->id, SoalSeeder::$bindIds);
        $this->assignPeserta($jadwalMendatang->id, $allPeserta);

        // ── 5. Draft: soal sudah dilampirkan, belum ada peserta ───────────────
        $jadwalDraft = JadwalUjian::create([
            'kode_jadwal'     => 'UAS-DRAFT-001',
            'nama_ujian'      => 'UAS Genap — Simulasi Draft (Belum Dipublikasikan)',
            'deskripsi'       => 'Jadwal dalam tahap persiapan. Soal sudah dilampirkan, peserta belum di-assign. '
                . 'Status masih draft, transisi ke terbuka bisa dilakukan admin.',
            'waktu_mulai'     => now()->addDays(7)->setTime(8, 0, 0),
            'waktu_selesai'   => now()->addDays(7)->setTime(9, 30, 0),
            'durasi_menit'    => 90,
            'acak_soal'       => false,
            'acak_opsi'       => false,
            'tampilkan_hasil' => true,
            'passing_grade'   => 75,
            'status'          => StatusJadwal::Draft,
            'created_by'      => $adminId,
        ]);
        $this->attachSoal($jadwalDraft->id, array_slice(SoalSeeder::$tikIds, 0, 10));
        // Tidak assign peserta — jadwal masih draft
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function attachSoal(int $jadwalId, array $soalIds): void
    {
        foreach ($soalIds as $i => $soalId) {
            JadwalSoal::create([
                'jadwal_ujian_id' => $jadwalId,
                'soal_id'         => $soalId,
                'nomor_urut'      => $i + 1,
            ]);
        }
    }

    private function assignPeserta(int $jadwalId, $pesertaIds): void
    {
        foreach ($pesertaIds as $userId) {
            JadwalPeserta::create([
                'jadwal_ujian_id' => $jadwalId,
                'user_id'         => $userId,
                'token_akses'     => bin2hex(random_bytes(32)), // 64-char hex
            ]);

            SesiUjian::create([
                'jadwal_ujian_id' => $jadwalId,
                'user_id'         => $userId,
                'status'          => 'belum_mulai',
            ]);
        }
    }
}
