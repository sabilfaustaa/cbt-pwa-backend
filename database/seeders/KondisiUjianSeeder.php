<?php

namespace Database\Seeders;

use App\Enums\StatusJadwal;
use App\Enums\StatusSesi;
use App\Enums\TipeSoal;
use App\Models\JadwalPeserta;
use App\Models\JadwalSoal;
use App\Models\JadwalUjian;
use App\Models\Jawaban;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  KondisiUjianSeeder — data edge-case untuk AUDIT FINALISASI alur ujian.       ║
 * ║  Rujukan: cbt-pwa/markdown/AUDIT_FINALISASI.md                                ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  CARA PAKAI (STANDALONE — TIDAK didaftarkan di DatabaseSeeder):                ║
 * ║    php artisan db:seed --class=KondisiUjianSeeder                              ║
 * ║                                                                               ║
 * ║  Prasyarat: DatabaseSeeder utama sudah dijalankan (butuh users A001–A010 &     ║
 * ║  bank soal). Seeder ini MEMBUAT jadwal terpisah (prefix KONDISI-*) dan         ║
 * ║  MENGHAPUS HANYA jadwal KONDISI-* miliknya sendiri sebelum re-seed —          ║
 * ║  TIDAK menyentuh data seeder lain (UAS-*).                                     ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Kenapa standalone & bukan di DatabaseSeeder?                                  ║
 * ║  Feature test men-seed DatabaseSeeder di beforeEach; menambah sesi baru        ║
 * ║  di sana akan menggeser resolusi `SesiUjian::first()` yang dipakai fixture     ║
 * ║  test (lihat temuan T-05 di AUDIT_FINALISASI.md). Dipisah = nol dampak ke      ║
 * ║  suite test & metrik paritas.                                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  PETA KONDISI (semua waktu relatif now(); peserta A001–A010 dari UserSeeder)   ║
 * ║  ── Jadwal KONDISI-BUKA (terbuka, window aktif selama 7 hari uji) ──────────   ║
 * ║   A001  K1  belum_mulai      → kontrol positif: boleh mulai sekarang           ║
 * ║   A002  K2  sedang_berlangsung, sisa ±40 detik → uji timer habis LIVE          ║
 * ║   A003  K3  sedang_berlangsung, OVERTIME (batas -3 mnt), ADA jawaban           ║
 * ║             → kandidat auto-submit scheduler (skor MASIH kosong)               ║
 * ║   A004  K4  KADALUARSA tanpa skor, ADA jawaban                                 ║
 * ║             → replika bug heartbeat (F-01): sesi kadaluarsa skor NULL          ║
 * ║   A005  K5  selesai, tampilkan_hasil=TRUE, skor terisi                         ║
 * ║             → uji HasilUjianPage + kebocoran soal sesi selesai (F-02)          ║
 * ║   A006  K6  sedang_berlangsung, jawaban PG GANDA (lama=salah, baru=benar)      ║
 * ║             → replika bug ganti-jawaban (F-03): scoring pilih baris lama       ║
 * ║   A007  K7  dibatalkan pengawas                                                ║
 * ║   A008  K8  sedang_berlangsung normal (progress 4 soal) → baseline             ║
 * ║   A009  K9  selesai, tampilkan_hasil=FALSE → uji gating hasil (403 peserta)    ║
 * ║  ── Jadwal KONDISI-NANTI (terbuka, belum buka sampai periode uji selesai) ──   ║
 * ║   A010  K10 belum_mulai → uji "masuk lebih awal": mulai=409, tapi cek F-02     ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */
class KondisiUjianSeeder extends Seeder
{
    private const TESTING_WINDOW_DAYS = 7;

    private ScoringService $scoringService;

    public function run(): void
    {
        $this->scoringService = app(ScoringService::class);
        $seededAt = now();

        $adminId = User::where('email', 'admin@cbt.test')->value('id');
        if (! $adminId) {
            $this->command?->error('KondisiUjianSeeder: admin@cbt.test tidak ada. Jalankan DatabaseSeeder dulu.');

            return;
        }

        // Peserta A001–A010 (dari UserSeeder)
        $peserta = User::whereIn('no_agenda', [
            'A001', 'A002', 'A003', 'A004', 'A005',
            'A006', 'A007', 'A008', 'A009', 'A010',
        ])->orderBy('no_agenda')->get()->keyBy('no_agenda');

        if ($peserta->count() < 10) {
            $this->command?->error('KondisiUjianSeeder: butuh peserta A001–A010. Jalankan DatabaseSeeder dulu.');

            return;
        }

        // Bank soal 4 tipe (dari SoalSeeder) — diquery dari DB agar tidak
        // bergantung pada static array SoalSeeder (yang kosong di run standalone).
        $soal = $this->ambilSoalEmpatTipe();
        if ($soal->isEmpty()) {
            $this->command?->error('KondisiUjianSeeder: bank soal kosong. Jalankan DatabaseSeeder dulu.');

            return;
        }

        DB::transaction(function () use ($adminId, $peserta, $soal, $seededAt) {
            $this->bersihkanKondisiLama();

            $testingWindowEndsAt = $seededAt->copy()->addDays(self::TESTING_WINDOW_DAYS)->endOfDay();
            $lockedExamStartsAt = $seededAt->copy()->addDays(self::TESTING_WINDOW_DAYS + 1)->setTime(8, 0, 0);

            $jadwalBuka = $this->buatJadwal($adminId, [
                'kode_jadwal' => 'KONDISI-BUKA',
                'nama_ujian' => 'AUDIT — Kondisi Alur Ujian (Window Aktif)',
                'deskripsi' => 'Jadwal audit finalisasi. Window aktif selama periode testing 7 hari.',
                'waktu_mulai' => $seededAt->copy()->subMinutes(10),
                'waktu_selesai' => $testingWindowEndsAt,
                'durasi_menit' => 60,
                'passing_grade' => 65,
                'tampilkan_hasil' => true,
                'status' => StatusJadwal::Terbuka,
            ], $soal);

            $jadwalNanti = $this->buatJadwal($adminId, [
                'kode_jadwal' => 'KONDISI-NANTI',
                'nama_ujian' => 'AUDIT — Ujian Belum Dibuka (Mulai +3 Jam)',
                'deskripsi' => 'Jadwal audit finalisasi. Window belum buka sampai periode testing selesai.',
                'waktu_mulai' => $lockedExamStartsAt->copy(),
                'waktu_selesai' => $lockedExamStartsAt->copy()->addHour(),
                'durasi_menit' => 60,
                'passing_grade' => 75,
                'tampilkan_hasil' => true,
                'status' => StatusJadwal::Terbuka,
            ], $soal);

            // ── K1: belum_mulai, window aktif (kontrol positif) ──────────────
            $this->buatSesi($jadwalBuka, $peserta['A001'], StatusSesi::BelumMulai);

            // ── K2: sedang_berlangsung, sisa ±40 detik ───────────────────────
            $s = $this->buatSesi($jadwalBuka, $peserta['A002'], StatusSesi::SedangBerlangsung, [
                'waktu_mulai' => now()->subMinutes(59)->subSeconds(20),
                'waktu_batas' => now()->addSeconds(40),
            ]);
            $this->jawabSebagian($s, $soal, 4, benar: true);

            // ── K3: sedang_berlangsung OVERTIME (batas lewat 3 mnt), skor kosong
            $s = $this->buatSesi($jadwalBuka, $peserta['A003'], StatusSesi::SedangBerlangsung, [
                'waktu_mulai' => now()->subMinutes(63),
                'waktu_batas' => now()->subMinutes(3),
            ]);
            $this->jawabSebagian($s, $soal, 6, benar: true);

            // ── K4: KADALUARSA tanpa skor (replika bug heartbeat F-01) ───────
            $s = $this->buatSesi($jadwalBuka, $peserta['A004'], StatusSesi::Kadaluarsa, [
                'waktu_mulai' => now()->subMinutes(70),
                'waktu_batas' => now()->subMinutes(10),
                'waktu_selesai' => now()->subMinutes(10),
            ]);
            $this->jawabSebagian($s, $soal, 8, benar: true);
            // Sengaja TIDAK di-score → skor_* tetap NULL (kondisi bug yang diaudit)

            // ── K5: selesai, tampilkan_hasil=true, skor terisi ───────────────
            $s = $this->buatSesi($jadwalBuka, $peserta['A005'], StatusSesi::Selesai, [
                'waktu_mulai' => now()->subMinutes(80),
                'waktu_batas' => now()->subMinutes(20),
                'waktu_selesai' => now()->subMinutes(35),
            ]);
            $this->jawabSemua($s, $soal, benar: true);
            $this->scoreDanSimpan($s);

            // ── K6: sedang_berlangsung, jawaban PG GANDA (F-03) ──────────────
            $s = $this->buatSesi($jadwalBuka, $peserta['A006'], StatusSesi::SedangBerlangsung, [
                'waktu_mulai' => now()->subMinutes(20),
                'waktu_batas' => now()->addMinutes(40),
            ]);
            $this->jawabGandaPg($s, $soal);

            // ── K7: dibatalkan ───────────────────────────────────────────────
            $this->buatSesi($jadwalBuka, $peserta['A007'], StatusSesi::Dibatalkan, [
                'waktu_mulai' => now()->subMinutes(30),
                'waktu_batas' => now()->addMinutes(30),
                'waktu_selesai' => now()->subMinutes(15),
            ]);

            // ── K8: sedang_berlangsung normal (baseline) ─────────────────────
            $s = $this->buatSesi($jadwalBuka, $peserta['A008'], StatusSesi::SedangBerlangsung, [
                'waktu_mulai' => now()->subMinutes(15),
                'waktu_batas' => now()->addMinutes(45),
            ]);
            $this->jawabSebagian($s, $soal, 4, benar: false);

            // ── K9: selesai, tampilkan_hasil=false (gating) ──────────────────
            // Jadwal ini tampilkan_hasil=true, jadi buat sub-jadwal khusus.
            $jadwalTutupHasil = $this->buatJadwal($adminId, [
                'kode_jadwal' => 'KONDISI-TUTUPHASIL',
                'nama_ujian' => 'AUDIT — Hasil Disembunyikan (tampilkan_hasil=false)',
                'deskripsi' => 'Uji gating: peserta tidak boleh lihat hasil.',
                'waktu_mulai' => now()->subMinutes(90),
                'waktu_selesai' => now()->subMinutes(20),
                'durasi_menit' => 60,
                'passing_grade' => 70,
                'tampilkan_hasil' => false,
                'status' => StatusJadwal::Selesai,
            ], $soal);
            $s = $this->buatSesi($jadwalTutupHasil, $peserta['A009'], StatusSesi::Selesai, [
                'waktu_mulai' => now()->subMinutes(85),
                'waktu_batas' => now()->subMinutes(25),
                'waktu_selesai' => now()->subMinutes(40),
            ]);
            $this->jawabSemua($s, $soal, benar: true);
            $this->scoreDanSimpan($s);

            // ── K10: belum_mulai pada jadwal yang belum buka (early access) ──
            $this->buatSesi($jadwalNanti, $peserta['A010'], StatusSesi::BelumMulai);
        });

        $this->cetakRingkasan();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Builder
    // ══════════════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $attr @param Collection<int, Soal> $soal */
    private function buatJadwal(int $adminId, array $attr, Collection $soal): JadwalUjian
    {
        $jadwal = JadwalUjian::create(array_merge([
            'acak_soal' => false,
            'acak_opsi' => false,
            'created_by' => $adminId,
        ], $attr));

        foreach ($soal->values() as $i => $s) {
            JadwalSoal::create([
                'jadwal_ujian_id' => $jadwal->id,
                'soal_id' => $s->id,
                'nomor_urut' => $i + 1,
            ]);
        }

        return $jadwal;
    }

    /** @param array<string, mixed> $override */
    private function buatSesi(JadwalUjian $jadwal, User $peserta, StatusSesi $status, array $override = []): SesiUjian
    {
        JadwalPeserta::create([
            'jadwal_ujian_id' => $jadwal->id,
            'user_id' => $peserta->id,
            'token_akses' => bin2hex(random_bytes(32)),
        ]);

        return SesiUjian::create(array_merge([
            'jadwal_ujian_id' => $jadwal->id,
            'user_id' => $peserta->id,
            'status' => $status,
            'ip_mulai' => '192.168.1.50',
            'user_agent_mulai' => 'Mozilla/5.0 (AuditSeeder)',
        ], $override));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Jawaban
    // ══════════════════════════════════════════════════════════════════════════

    /** @param Collection<int, Soal> $soal */
    private function jawabSemua(SesiUjian $sesi, Collection $soal, bool $benar): void
    {
        foreach ($soal as $s) {
            $this->jawabSatu($sesi, $s, $benar);
        }
    }

    /** @param Collection<int, Soal> $soal */
    private function jawabSebagian(SesiUjian $sesi, Collection $soal, int $n, bool $benar): void
    {
        foreach ($soal->take($n) as $s) {
            $this->jawabSatu($sesi, $s, $benar);
        }
    }

    private function jawabSatu(SesiUjian $sesi, Soal $soal, bool $benar): void
    {
        $tipe = $soal->tipe instanceof TipeSoal ? $soal->tipe : TipeSoal::from($soal->tipe);

        match ($tipe) {
            TipeSoal::Pg => $this->insertPg($sesi, $soal, $benar),
            TipeSoal::BenarSalah => $this->insertBs($sesi, $soal, $benar),
            TipeSoal::Labeling => $this->insertLabeling($sesi, $soal, $benar),
            TipeSoal::Menjodohkan => $this->insertMenjodohkan($sesi, $soal, $benar),
        };
    }

    private function insertPg(SesiUjian $sesi, Soal $soal, bool $benar): void
    {
        $kunci = $soal->opsi->firstWhere('is_kunci', true);
        $salah = $soal->opsi->firstWhere('is_kunci', false);
        $opsi = $benar ? $kunci : ($salah ?? $kunci);
        if (! $opsi) {
            return;
        }

        Jawaban::create([
            'sesi_ujian_id' => $sesi->id,
            'soal_id' => $soal->id,
            'opsi_id' => $opsi->id,
            'waktu_jawab' => now(),
        ]);
    }

    private function insertBs(SesiUjian $sesi, Soal $soal, bool $benar): void
    {
        $nilai = $benar ? (bool) $soal->jawaban_benar_bool : ! $soal->jawaban_benar_bool;

        Jawaban::create([
            'sesi_ujian_id' => $sesi->id,
            'soal_id' => $soal->id,
            'jawaban_bool' => $nilai,
            'waktu_jawab' => now(),
        ]);
    }

    private function insertLabeling(SesiUjian $sesi, Soal $soal, bool $benar): void
    {
        $opsiList = $soal->opsi->sortBy('nomor_urut')->values();
        $count = $opsiList->count();

        foreach ($opsiList as $opsi) {
            $nomor = $benar
                ? $opsi->nomor_urut
                : ((int) $opsi->nomor_urut % max(1, $count)) + 1;

            Jawaban::create([
                'sesi_ujian_id' => $sesi->id,
                'soal_id' => $soal->id,
                'opsi_id' => $opsi->id,
                'nomor_jawaban' => $nomor,
                'waktu_jawab' => now(),
            ]);
        }
    }

    private function insertMenjodohkan(SesiUjian $sesi, Soal $soal, bool $benar): void
    {
        // Konvensi ScoringService::evaluasiMenjodohkan → benar bila pasangan_opsi_id === opsi_id
        $opsiList = $soal->opsi->values();

        foreach ($opsiList as $idx => $opsi) {
            $pasangan = $benar
                ? $opsi->id
                : ($opsiList->get(($idx + 1) % max(1, $opsiList->count()))->id ?? $opsi->id);

            Jawaban::create([
                'sesi_ujian_id' => $sesi->id,
                'soal_id' => $soal->id,
                'opsi_id' => $opsi->id,
                'pasangan_opsi_id' => $pasangan,
                'waktu_jawab' => now(),
            ]);
        }
    }

    /**
     * F-03: peserta menjawab SALAH lalu MENGGANTI ke BENAR pada soal PG pertama.
     * Karena kunci upsert menyertakan opsi_id, penggantian membuat DUA baris
     * (bukan update). ScoringService lalu menilai baris LAMA (salah).
     *
     * @param  Collection<int, Soal>  $soal
     */
    private function jawabGandaPg(SesiUjian $sesi, Collection $soal): void
    {
        $soalPg = $soal->first(fn (Soal $s) => ($s->tipe instanceof TipeSoal ? $s->tipe : TipeSoal::from($s->tipe)) === TipeSoal::Pg);
        if (! $soalPg) {
            return;
        }

        $kunci = $soalPg->opsi->firstWhere('is_kunci', true);
        $salah = $soalPg->opsi->firstWhere('is_kunci', false);
        if (! $kunci || ! $salah) {
            return;
        }

        // Baris #1 (lebih dulu, id lebih kecil) = jawaban SALAH
        Jawaban::create([
            'sesi_ujian_id' => $sesi->id,
            'soal_id' => $soalPg->id,
            'opsi_id' => $salah->id,
            'waktu_jawab' => now()->subSeconds(30),
        ]);
        // Baris #2 = peserta mengganti ke jawaban BENAR
        Jawaban::create([
            'sesi_ujian_id' => $sesi->id,
            'soal_id' => $soalPg->id,
            'opsi_id' => $kunci->id,
            'waktu_jawab' => now(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scoring (mirror SesiController::selesai — skor_* diisi via assignment)
    // ══════════════════════════════════════════════════════════════════════════

    private function scoreDanSimpan(SesiUjian $sesi): void
    {
        $result = $this->scoringService->score($sesi);

        foreach ($result['jawaban_updates'] as $soalId => $update) {
            Jawaban::where('sesi_ujian_id', $sesi->id)
                ->where('soal_id', $soalId)
                ->update([
                    'is_benar' => $update['is_benar'],
                    'poin_didapat' => $update['poin_didapat'],
                ]);
        }

        $sesi->skor_pg = $result['skor_pg'];
        $sesi->skor_benar_salah = $result['skor_benar_salah'];
        $sesi->skor_labeling = $result['skor_labeling'];
        $sesi->skor_menjodohkan = $result['skor_menjodohkan'];
        $sesi->skor_total = $result['skor_total'];
        $sesi->is_lulus = $result['is_lulus'];
        $sesi->save();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Utilitas
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Ambil 10 soal berimbang 4 tipe (4 pg / 2 bs / 2 labeling / 2 menjodohkan)
     * dari bank soal yang sudah di-seed, lengkap dengan opsinya.
     *
     * @return Collection<int, Soal>
     */
    private function ambilSoalEmpatTipe(): Collection
    {
        $pg = Soal::where('tipe', TipeSoal::Pg->value)->with('opsi')->take(4)->get();
        $bs = Soal::where('tipe', TipeSoal::BenarSalah->value)->with('opsi')->take(2)->get();
        $lb = Soal::where('tipe', TipeSoal::Labeling->value)->with('opsi')->take(2)->get();
        $mj = Soal::where('tipe', TipeSoal::Menjodohkan->value)->with('opsi')->take(2)->get();

        return $pg->concat($bs)->concat($lb)->concat($mj)->values();
    }

    private function bersihkanKondisiLama(): void
    {
        $ids = JadwalUjian::withTrashed()
            ->whereIn('kode_jadwal', ['KONDISI-BUKA', 'KONDISI-NANTI', 'KONDISI-TUTUPHASIL'])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $sesiIds = SesiUjian::whereIn('jadwal_ujian_id', $ids)->pluck('id');
        if ($sesiIds->isNotEmpty()) {
            Jawaban::whereIn('sesi_ujian_id', $sesiIds)->delete();
            DB::table('sesi_aktivitas')->whereIn('sesi_ujian_id', $sesiIds)->delete();
        }
        SesiUjian::whereIn('jadwal_ujian_id', $ids)->delete();
        JadwalSoal::whereIn('jadwal_ujian_id', $ids)->delete();
        JadwalPeserta::whereIn('jadwal_ujian_id', $ids)->delete();
        JadwalUjian::withTrashed()->whereIn('id', $ids)->forceDelete();
    }

    private function cetakRingkasan(): void
    {
        $this->command?->info('KondisiUjianSeeder: 3 jadwal audit + 10 sesi kondisi dibuat.');
        $this->command?->line('  Jadwal: KONDISI-BUKA, KONDISI-NANTI, KONDISI-TUTUPHASIL');
        $this->command?->line('  Peserta uji: A001–A010 (login NIK + no_agenda; lihat UserSeeder).');
        $this->command?->line('  Peta kondisi lengkap: cbt-pwa/markdown/AUDIT_FINALISASI.md');
    }
}
