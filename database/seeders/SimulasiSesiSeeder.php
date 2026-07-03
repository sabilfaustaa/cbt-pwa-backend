<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\TipeSoal;
use App\Models\Jawaban;
use App\Models\JadwalUjian;
use App\Models\Soal;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mensimulasikan aktivitas ujian untuk SELURUH edge case yang dibutuhkan testing.
 *
 * Semua waktu relatif terhadap now() — reusable di hari manapun.
 *
 * ┌───────────────────────────────────────────────────────────────────────────┐
 * │ Jadwal               │ Skenario                                           │
 * ├───────────────────────────────────────────────────────────────────────────┤
 * │ UAS-2026-TIK      │ 30 sesi, SEMUA selesai (total_peserta=selesai=30):    │
 * │                      │  A001       : skor 100% (sempurna)                 │
 * │                      │  A002–A015  : skor 70–97% (lulus)                  │
 * │                      │  A016–A026  : skor 0–68% (tidak lulus)             │
 * │                      │  A027       : selesai 43%, 3 pelanggaran (gagal)   │
 * │                      │  A028–A029  : selesai skor rendah (< KKM)          │
 * │                      │  A030       : selesai 75%, 2 pelanggaran (lulus)   │
 * ├───────────────────────────────────────────────────────────────────────────┤
 * │ UAS-2026-MTK  │ 30 sesi beragam:                                   │
 * │                      │  A001–A005  : sedang_berlangsung (8/14 soal)       │
 * │                      │  A006–A007  : sedang_berlangsung (hampir habis)    │
 * │                      │  A008–A009  : sedang_berlangsung (OVERTIME)        │
 * │                      │  A010       : sedang_berlangsung (3× tab_blur)     │
 * │                      │  A011       : sedang_berlangsung (5× mix)          │
 * │                      │  A012       : sedang_berlangsung (0 soal)          │
 * │                      │  A013–A018  : selesai manual (skor bervariasi)     │
 * │                      │  A019–A023  : belum_mulai                           │
 * │                      │  A024–A025  : dibatalkan                            │
 * │                      │  A026–A027  : kadaluarsa                            │
 * │                      │  A028–A030  : sedang_berlangsung (3/14 soal)       │
 * ├───────────────────────────────────────────────────────────────────────────┤
 * │ UAS-BATAL-001        │ 10 sesi: semua dibatalkan                          │
 * └───────────────────────────────────────────────────────────────────────────┘
 *
 * Skor disimpan sebagai PERSENTASE per tipe (0–100), konsisten dengan ScoringService.
 * skor_total = rata-rata tipe yang ada di jadwal.
 */
class SimulasiSesiSeeder extends Seeder
{
    /**
     * Tingkat keberhasilan per no_agenda — dipakai untuk menentukan benar/salah secara deterministik.
     * 1.00 = semua soal benar, 0.00 = semua soal salah.
     */
    private const RATE = [
        'A001' => 1.00, 'A002' => 0.97, 'A003' => 0.94, 'A004' => 0.91, 'A005' => 0.90,
        'A006' => 0.88, 'A007' => 0.85, 'A008' => 0.82, 'A009' => 0.80, 'A010' => 0.77,
        'A011' => 0.76, 'A012' => 0.75, 'A013' => 0.73, 'A014' => 0.72, 'A015' => 0.70,
        'A016' => 0.68, 'A017' => 0.66, 'A018' => 0.64, 'A019' => 0.62, 'A020' => 0.60,
        'A021' => 0.55, 'A022' => 0.48, 'A023' => 0.43, 'A024' => 0.38, 'A025' => 0.33,
        'A026' => 0.00, 'A027' => 0.43, 'A028' => 0.38, 'A029' => 0.33, 'A030' => 0.75,
    ];

    /** Rates khusus untuk MTK selesai manual (A013–A018) */
    private const RATE_MTK_SELESAI = [
        'A013' => 0.85, 'A014' => 0.75, 'A015' => 0.65,
        'A016' => 0.55, 'A017' => 0.45, 'A018' => 0.35,
    ];

    // ──────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $pesertaList = User::whereHas('role', fn ($q) => $q->where('nama_role', RoleName::Peserta))
            ->orderBy('no_agenda')
            ->get()
            ->keyBy('no_agenda');

        $this->simulasiSelesaiTIK($pesertaList);
        $this->simulasiBerlangsungMTK($pesertaList);
        $this->simulasiBatal($pesertaList);
        // UAS-MENDATANG-BIND : semua belum_mulai (tidak perlu simulasi)
        // UAS-DRAFT-001      : tidak ada peserta
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. UAS-2026-TIK — semua 30 peserta, skor + jawaban lengkap
    // ══════════════════════════════════════════════════════════════════════════

    private function simulasiSelesaiTIK(Collection $pesertaList): void
    {
        $jadwal       = JadwalUjian::where('kode_jadwal', 'UAS-2026-TIK')->firstOrFail();
        $soalList     = $this->getSoalJadwal($jadwal->id);
        $passingGrade = (float) $jadwal->passing_grade; // 70

        foreach ($pesertaList as $noAgenda => $peserta) {
            $sesi = SesiUjian::where('jadwal_ujian_id', $jadwal->id)
                ->where('user_id', $peserta->id)
                ->firstOrFail();

            // ── Selesai dengan pelanggaran (A027): skor rendah, 3 tab_blur ──
            // Sesi tetap selesai agar total_peserta=selesai=30 untuk AnalitikTest.
            // Kasus dibatalkan ada di MTK untuk coverage test yang berbeda.
            if ($noAgenda === 'A027') {
                $rate = self::RATE[$noAgenda] ?? 0.43;
                [$skorPg, $skorBs, $skorLb, $skorMj, $skorTotal] =
                    $this->buatJawaban($sesi->id, $soalList, $noAgenda, $rate);

                $wm          = $jadwal->waktu_mulai->copy()->addMinutes(2);
                $durasiAktual = max(15, (int)(90 * (0.4 + $rate * 0.55)));
                $ws          = $wm->copy()->addMinutes($durasiAktual);

                DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                    'status'             => 'selesai',
                    'waktu_mulai'        => $wm,
                    'waktu_batas'        => $jadwal->waktu_mulai->copy()->addMinutes($jadwal->durasi_menit),
                    'waktu_selesai'      => $ws,
                    'ip_mulai'           => '192.168.1.127',
                    'user_agent_mulai'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                    'jumlah_pelanggaran' => 3,
                    'persetujuan_at'     => $wm,
                    'ip_persetujuan'     => '192.168.1.127',
                    'skor_pg'            => $skorPg,
                    'skor_benar_salah'   => $skorBs,
                    'skor_labeling'      => $skorLb,
                    'skor_menjodohkan'   => $skorMj,
                    'skor_total'         => $skorTotal,
                    'is_lulus'           => $skorTotal >= $passingGrade,
                ]);
                $this->buatAktivitasPelanggaran($sesi->id, [
                    ['jenis' => 'tab_blur', 'menit_lalu' => 10],
                    ['jenis' => 'tab_blur', 'menit_lalu' => 8],
                    ['jenis' => 'tab_blur', 'menit_lalu' => 6],
                ]);
                continue;
            }

            // ── Selesai skor rendah (A028–A029): semua soal dijawab, nilai < KKM ──
            // Kasus kadaluarsa ada di MTK untuk coverage test yang berbeda.
            if (in_array($noAgenda, ['A028', 'A029'], true)) {
                $rate = self::RATE[$noAgenda] ?? 0.35;
                [$skorPg, $skorBs, $skorLb, $skorMj, $skorTotal] =
                    $this->buatJawaban($sesi->id, $soalList, $noAgenda, $rate);

                $wm          = $jadwal->waktu_mulai->copy()->addMinutes(1);
                $durasiAktual = max(15, (int)(90 * (0.4 + $rate * 0.55)));
                $ws          = $wm->copy()->addMinutes($durasiAktual);

                DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                    'status'             => 'selesai',
                    'waktu_mulai'        => $wm,
                    'waktu_batas'        => $jadwal->waktu_mulai->copy()->addMinutes($jadwal->durasi_menit),
                    'waktu_selesai'      => $ws,
                    'ip_mulai'           => '192.168.1.' . random_int(10, 200),
                    'user_agent_mulai'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                    'jumlah_pelanggaran' => 0,
                    'persetujuan_at'     => $wm,
                    'ip_persetujuan'     => '192.168.1.' . random_int(10, 200),
                    'skor_pg'            => $skorPg,
                    'skor_benar_salah'   => $skorBs,
                    'skor_labeling'      => $skorLb,
                    'skor_menjodohkan'   => $skorMj,
                    'skor_total'         => $skorTotal,
                    'is_lulus'           => false,
                ]);
                continue;
            }

            // ── Selesai normal (A001–A026, A030) ──
            $rate = self::RATE[$noAgenda] ?? 0.50;
            [$skorPg, $skorBs, $skorLb, $skorMj, $skorTotal] =
                $this->buatJawaban($sesi->id, $soalList, $noAgenda, $rate);

            $wm           = $jadwal->waktu_mulai->copy()->addMinutes(random_int(0, 5));
            $durasiAktual = max(15, (int)(90 * (0.4 + $rate * 0.55)));
            $ws           = $wm->copy()->addMinutes($durasiAktual);

            $pelanggaran = ($noAgenda === 'A030') ? 2 : 0;

            DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                'status'             => 'selesai',
                'waktu_mulai'        => $wm,
                'waktu_batas'        => $jadwal->waktu_mulai->copy()->addMinutes($jadwal->durasi_menit),
                'waktu_selesai'      => $ws,
                'ip_mulai'           => '192.168.1.' . random_int(10, 200),
                'user_agent_mulai'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                'jumlah_pelanggaran' => $pelanggaran,
                'persetujuan_at'     => $wm,
                'ip_persetujuan'     => '192.168.1.' . random_int(10, 200),
                'skor_pg'            => $skorPg,
                'skor_benar_salah'   => $skorBs,
                'skor_labeling'      => $skorLb,
                'skor_menjodohkan'   => $skorMj,
                'skor_total'         => $skorTotal,
                'is_lulus'           => $skorTotal >= $passingGrade,
            ]);

            if ($noAgenda === 'A030') {
                $this->buatAktivitasPelanggaran($sesi->id, [
                    ['jenis' => 'fullscreen_exit', 'menit_lalu' => 40],
                    ['jenis' => 'tab_blur',        'menit_lalu' => 25],
                ]);
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. UAS-2026-MTK — berbagai skenario sesi
    // ══════════════════════════════════════════════════════════════════════════

    private function simulasiBerlangsungMTK(Collection $pesertaList): void
    {
        $jadwal       = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->firstOrFail();
        $soalList     = $this->getSoalJadwal($jadwal->id);
        $passingGrade = (float) $jadwal->passing_grade; // 65
        $wmJadwal     = $jadwal->waktu_mulai; // now()-35min

        foreach ($pesertaList as $noAgenda => $peserta) {
            $sesi = SesiUjian::where('jadwal_ujian_id', $jadwal->id)
                ->where('user_id', $peserta->id)
                ->firstOrFail();

            // ── A001–A005: sedang berlangsung normal, 8/14 soal dijawab ───
            if (in_array($noAgenda, ['A001', 'A002', 'A003', 'A004', 'A005'], true)) {
                $wm = $wmJadwal->copy()->addMinutes(random_int(0, 3));
                $this->buatJawaban($sesi->id, $soalList->take(8), $noAgenda, self::RATE[$noAgenda] ?? 0.80);
                DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                    'status'           => 'sedang_berlangsung',
                    'waktu_mulai'      => $wm,
                    'waktu_batas'      => now()->addMinutes(60),
                    'ip_mulai'         => '192.168.1.' . random_int(10, 200),
                    'user_agent_mulai' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                    'persetujuan_at'   => $wm,
                    'ip_persetujuan'   => '192.168.1.' . random_int(10, 200),
                ]);
                continue;
            }

            // ── A006–A007: hampir habis waktu (waktu_batas +3 menit) ──────
            if (in_array($noAgenda, ['A006', 'A007'], true)) {
                $wm = $wmJadwal->copy()->addMinutes(1);
                $this->buatJawaban($sesi->id, $soalList->take(12), $noAgenda, self::RATE[$noAgenda] ?? 0.85);
                DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                    'status'           => 'sedang_berlangsung',
                    'waktu_mulai'      => $wm,
                    'waktu_batas'      => now()->addMinutes(3), // hampir habis!
                    'ip_mulai'         => '192.168.1.' . random_int(10, 200),
                    'user_agent_mulai' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                    'persetujuan_at'   => $wm,
                    'ip_persetujuan'   => '192.168.1.' . random_int(10, 200),
                ]);
                continue;
            }

            // ── A008–A009: OVERTIME — waktu_batas sudah lewat ─────────────
            // → kandidat untuk auto-submit scheduler
            if (in_array($noAgenda, ['A008', 'A009'], true)) {
                $wm = $wmJadwal->copy();
                $this->buatJawaban($sesi->id, $soalList->take(10), $noAgenda, self::RATE[$noAgenda] ?? 0.80);
                DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                    'status'           => 'sedang_berlangsung',
                    'waktu_mulai'      => $wm,
                    'waktu_batas'      => now()->subMinutes(5), // sudah melewati batas!
                    'ip_mulai'         => '192.168.1.' . random_int(10, 200),
                    'user_agent_mulai' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                    'persetujuan_at'   => $wm,
                    'ip_persetujuan'   => '192.168.1.' . random_int(10, 200),
                ]);
                continue;
            }

            // ── A010: sedang berlangsung, 3 pelanggaran tab_blur ──────────
            if ($noAgenda === 'A010') {
                $wm = $wmJadwal->copy()->addMinutes(2);
                $this->buatJawaban($sesi->id, $soalList->take(5), $noAgenda, self::RATE[$noAgenda] ?? 0.77);
                DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                    'status'             => 'sedang_berlangsung',
                    'waktu_mulai'        => $wm,
                    'waktu_batas'        => now()->addMinutes(60),
                    'ip_mulai'           => '192.168.1.110',
                    'user_agent_mulai'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                    'jumlah_pelanggaran' => 3,
                    'persetujuan_at'     => $wm,
                    'ip_persetujuan'     => '192.168.1.110',
                ]);
                $this->buatAktivitasPelanggaran($sesi->id, [
                    ['jenis' => 'tab_blur', 'menit_lalu' => 20],
                    ['jenis' => 'tab_blur', 'menit_lalu' => 15],
                    ['jenis' => 'tab_blur', 'menit_lalu' => 10],
                ]);
                continue;
            }

            // ── A011: sedang berlangsung, 5 pelanggaran campuran ─────────
            if ($noAgenda === 'A011') {
                $wm = $wmJadwal->copy()->addMinutes(1);
                $this->buatJawaban($sesi->id, $soalList->take(7), $noAgenda, self::RATE[$noAgenda] ?? 0.76);
                DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                    'status'             => 'sedang_berlangsung',
                    'waktu_mulai'        => $wm,
                    'waktu_batas'        => now()->addMinutes(60),
                    'ip_mulai'           => '192.168.1.111',
                    'user_agent_mulai'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                    'jumlah_pelanggaran' => 5,
                    'persetujuan_at'     => $wm,
                    'ip_persetujuan'     => '192.168.1.111',
                ]);
                $this->buatAktivitasPelanggaran($sesi->id, [
                    ['jenis' => 'tab_blur',        'menit_lalu' => 28],
                    ['jenis' => 'fullscreen_exit', 'menit_lalu' => 22],
                    ['jenis' => 'tab_blur',        'menit_lalu' => 18],
                    ['jenis' => 'dev_tools_open',  'menit_lalu' => 12],
                    ['jenis' => 'tab_blur',        'menit_lalu' => 8],
                ]);
                continue;
            }

            // ── A012: sedang berlangsung, baru saja mulai (0 soal dijawab) ─
            if ($noAgenda === 'A012') {
                $wm = now()->subMinutes(2);
                DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                    'status'           => 'sedang_berlangsung',
                    'waktu_mulai'      => $wm,
                    'waktu_batas'      => $wm->copy()->addMinutes($jadwal->durasi_menit),
                    'ip_mulai'         => '192.168.1.112',
                    'user_agent_mulai' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                    'persetujuan_at'   => $wm,
                    'ip_persetujuan'   => '192.168.1.112',
                ]);
                continue;
            }

            // ── A013–A018: selesai manual, skor bervariasi ───────────────
            if (isset(self::RATE_MTK_SELESAI[$noAgenda])) {
                $rate = self::RATE_MTK_SELESAI[$noAgenda];
                [$skorPg, $skorBs, $skorLb, $skorMj, $skorTotal] =
                    $this->buatJawaban($sesi->id, $soalList, $noAgenda, $rate);
                $wm = $wmJadwal->copy()->addMinutes(2);
                DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                    'status'           => 'selesai',
                    'waktu_mulai'      => $wm,
                    'waktu_batas'      => $wm->copy()->addMinutes($jadwal->durasi_menit),
                    'waktu_selesai'    => now()->subMinutes(10), // selesai 10 menit lalu
                    'ip_mulai'         => '192.168.1.' . random_int(10, 200),
                    'user_agent_mulai' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                    'persetujuan_at'   => $wm,
                    'ip_persetujuan'   => '192.168.1.' . random_int(10, 200),
                    'skor_pg'          => $skorPg,
                    'skor_benar_salah' => $skorBs,
                    'skor_labeling'    => $skorLb,
                    'skor_menjodohkan' => $skorMj,
                    'skor_total'       => $skorTotal,
                    'is_lulus'         => $skorTotal >= $passingGrade,
                ]);
                continue;
            }

            // ── A019–A023: belum mulai (tidak ada update) ─────────────────
            if (in_array($noAgenda, ['A019', 'A020', 'A021', 'A022', 'A023'], true)) {
                continue;
            }

            // ── A024–A025: dibatalkan oleh pengawas ───────────────────────
            if (in_array($noAgenda, ['A024', 'A025'], true)) {
                $wm = $wmJadwal->copy()->addMinutes(5);
                DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                    'status'           => 'dibatalkan',
                    'waktu_mulai'      => $wm,
                    'waktu_batas'      => $wm->copy()->addMinutes($jadwal->durasi_menit),
                    'ip_mulai'         => '192.168.1.' . random_int(10, 200),
                    'user_agent_mulai' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                    'persetujuan_at'   => $wm,
                    'ip_persetujuan'   => '192.168.1.' . random_int(10, 200),
                ]);
                continue;
            }

            // ── A026–A027: kadaluarsa (waktu habis, auto-submit) ─────────
            if (in_array($noAgenda, ['A026', 'A027'], true)) {
                $rate = self::RATE[$noAgenda] ?? 0.40;
                $wm   = $wmJadwal->copy()->addMinutes(2);
                [$skorPg, $skorBs, $skorLb, $skorMj, $skorTotal] =
                    $this->buatJawaban($sesi->id, $soalList->take(7), $noAgenda, $rate);
                $wb = now()->subMinutes(random_int(5, 15)); // waktu_batas sudah lewat
                DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                    'status'           => 'kadaluarsa',
                    'waktu_mulai'      => $wm,
                    'waktu_batas'      => $wb,
                    'waktu_selesai'    => $wb, // selesai = saat timeout
                    'ip_mulai'         => '192.168.1.' . random_int(10, 200),
                    'user_agent_mulai' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                    'persetujuan_at'   => $wm,
                    'ip_persetujuan'   => '192.168.1.' . random_int(10, 200),
                    'skor_pg'          => $skorPg,
                    'skor_benar_salah' => $skorBs,
                    'skor_labeling'    => $skorLb,
                    'skor_menjodohkan' => $skorMj,
                    'skor_total'       => $skorTotal,
                    'is_lulus'         => false,
                ]);
                continue;
            }

            // ── A028–A030: sedang berlangsung, progress awal (3/14 soal) ──
            $wm = $wmJadwal->copy()->addMinutes(random_int(5, 15));
            $this->buatJawaban($sesi->id, $soalList->take(3), $noAgenda, self::RATE[$noAgenda] ?? 0.50);
            DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                'status'           => 'sedang_berlangsung',
                'waktu_mulai'      => $wm,
                'waktu_batas'      => now()->addMinutes(60),
                'ip_mulai'         => '192.168.1.' . random_int(10, 200),
                'user_agent_mulai' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0',
                'persetujuan_at'   => $wm,
                'ip_persetujuan'   => '192.168.1.' . random_int(10, 200),
            ]);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. UAS-BATAL-001 — 10 peserta, semua sesi dibatalkan
    // ══════════════════════════════════════════════════════════════════════════

    private function simulasiBatal(Collection $pesertaList): void
    {
        $jadwal = JadwalUjian::where('kode_jadwal', 'UAS-BATAL-001')->firstOrFail();

        foreach ($pesertaList->take(10) as $peserta) {
            $sesi = SesiUjian::where('jadwal_ujian_id', $jadwal->id)
                ->where('user_id', $peserta->id)
                ->first();

            if (! $sesi) {
                continue;
            }

            DB::table('sesi_ujian')->where('id', $sesi->id)->update([
                'status' => 'dibatalkan',
            ]);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Helper: Buat jawaban + hitung skor
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Buat jawaban untuk soal-soal yang diberikan dan kembalikan skor per tipe (%).
     *
     * Benar/salah ditentukan secara deterministik: seed = |crc32("{noAgenda}-{soalId}")| % 100.
     * Labeling & Menjodohkan: all-or-nothing per soal (konsisten dengan ScoringService).
     *
     * @return array{0: float|null, 1: float|null, 2: float|null, 3: float|null, 4: float}
     *         [skor_pg, skor_benar_salah, skor_labeling, skor_menjodohkan, skor_total]
     */
    private function buatJawaban(int $sesiId, Collection $soalList, string $noAgenda, float $rate): array
    {
        $pgB = 0; $pgT = 0;
        $bsB = 0; $bsT = 0;
        $lbB = 0; $lbT = 0;
        $mjB = 0; $mjT = 0;

        foreach ($soalList as $soal) {
            $tipe    = $soal->tipe instanceof TipeSoal ? $soal->tipe : TipeSoal::from($soal->tipe);
            $isBenar = $this->deterministikBenar($noAgenda, $soal->id, $rate);

            match ($tipe) {
                TipeSoal::Pg => (function () use ($sesiId, $soal, $isBenar, &$pgB, &$pgT) {
                    $pgT++;
                    if ($isBenar) {
                        $pgB++;
                    }
                    $this->insertJawabanPg($sesiId, $soal, $isBenar);
                })(),

                TipeSoal::BenarSalah => (function () use ($sesiId, $soal, $isBenar, &$bsB, &$bsT) {
                    $bsT++;
                    if ($isBenar) {
                        $bsB++;
                    }
                    $this->insertJawabanBs($sesiId, $soal, $isBenar);
                })(),

                TipeSoal::Labeling => (function () use ($sesiId, $soal, $isBenar, &$lbB, &$lbT) {
                    $lbT++;
                    if ($isBenar) {
                        $lbB++;
                    }
                    $this->insertJawabanLabeling($sesiId, $soal, $isBenar);
                })(),

                TipeSoal::Menjodohkan => (function () use ($sesiId, $soal, $isBenar, &$mjB, &$mjT) {
                    $mjT++;
                    if ($isBenar) {
                        $mjB++;
                    }
                    $this->insertJawabanMenjodohkan($sesiId, $soal, $isBenar);
                })(),
            };
        }

        return $this->hitungSkor($pgB, $pgT, $bsB, $bsT, $lbB, $lbT, $mjB, $mjT);
    }

    // ── Handler insert per tipe ────────────────────────────────────────────────

    private function insertJawabanPg(int $sesiId, Soal $soal, bool $isBenar): void
    {
        $opsiBenar = $soal->opsi->firstWhere('is_kunci', true);
        $opsiSalah = $soal->opsi->where('is_kunci', false)->values();

        if (! $opsiBenar) {
            return;
        }

        $opsiDipilih = $isBenar
            ? $opsiBenar
            : $opsiSalah->get(abs(crc32("{$soal->id}")) % max(1, $opsiSalah->count()));

        if (! $opsiDipilih) {
            return;
        }

        Jawaban::create([
            'sesi_ujian_id' => $sesiId,
            'soal_id'       => $soal->id,
            'opsi_id'       => $opsiDipilih->id,
            'is_benar'      => $isBenar,
            'poin_didapat'  => $isBenar ? (float) $soal->poin : 0.0,
            'waktu_jawab'   => now(),
        ]);
    }

    private function insertJawabanBs(int $sesiId, Soal $soal, bool $isBenar): void
    {
        $jawabanBool = $isBenar
            ? $soal->jawaban_benar_bool
            : ! $soal->jawaban_benar_bool;

        Jawaban::create([
            'sesi_ujian_id' => $sesiId,
            'soal_id'       => $soal->id,
            'opsi_id'       => null,
            'jawaban_bool'  => $jawabanBool,
            'is_benar'      => $isBenar,
            'poin_didapat'  => $isBenar ? (float) $soal->poin : 0.0,
            'waktu_jawab'   => now(),
        ]);
    }

    /**
     * Labeling: all-or-nothing per soal.
     * Benar  → nomor_jawaban = opsi.nomor_urut (urutan benar).
     * Salah  → nomor_jawaban yang salah secara deterministik.
     */
    private function insertJawabanLabeling(int $sesiId, Soal $soal, bool $isBenarSoal): void
    {
        $opsiList = $soal->opsi->sortBy('nomor_urut')->values();
        $count    = $opsiList->count();

        foreach ($opsiList as $opsi) {
            if ($isBenarSoal) {
                $nomorJawab = $opsi->nomor_urut;
            } else {
                $wrong = ($opsi->nomor_urut % $count) + 1;
                if ($wrong === $opsi->nomor_urut) {
                    $wrong = ($wrong % $count) + 1;
                }
                $nomorJawab = $wrong;
            }

            Jawaban::create([
                'sesi_ujian_id' => $sesiId,
                'soal_id'       => $soal->id,
                'opsi_id'       => $opsi->id,
                'nomor_jawaban' => $nomorJawab,
                'is_benar'      => $isBenarSoal,
                'poin_didapat'  => $isBenarSoal ? round((float) $soal->poin / $count, 4) : 0.0,
                'waktu_jawab'   => now(),
            ]);
        }
    }

    /**
     * Menjodohkan: all-or-nothing per soal.
     * Benar  → pasangan_opsi_id = opsi kanan yang benar (dari SoalSeeder::$menjodohkanCorrectMap).
     * Salah  → pasangan_opsi_id yang salah secara deterministik.
     */
    private function insertJawabanMenjodohkan(int $sesiId, Soal $soal, bool $isBenarSoal): void
    {
        $semuaOpsi = $soal->opsi->sortBy('nomor_urut')->values();
        $half      = (int) ceil($semuaOpsi->count() / 2);
        $opsiKiri  = $semuaOpsi->take($half)->values();
        $opsiKanan = $semuaOpsi->slice($half)->values();
        $poinItem  = $half > 0 ? round((float) $soal->poin / $half, 4) : 0.0;

        foreach ($opsiKiri as $idx => $kiri) {
            $pasanganBenarId = SoalSeeder::$menjodohkanCorrectMap[$kiri->id]
                ?? ($opsiKanan->get($idx)?->id);

            if ($isBenarSoal) {
                $pasanganDipilih = $pasanganBenarId;
            } else {
                $wrongIdx        = ($idx + 1) % $opsiKanan->count();
                $pasanganSalah   = $opsiKanan->get($wrongIdx)?->id;
                $pasanganDipilih = ($pasanganSalah !== $pasanganBenarId)
                    ? $pasanganSalah
                    : $opsiKanan->get(($idx + 2) % $opsiKanan->count())?->id;
            }

            if (! $pasanganDipilih) {
                continue;
            }

            Jawaban::create([
                'sesi_ujian_id'    => $sesiId,
                'soal_id'          => $soal->id,
                'opsi_id'          => $kiri->id,
                'pasangan_opsi_id' => $pasanganDipilih,
                'is_benar'         => $isBenarSoal,
                'poin_didapat'     => $isBenarSoal ? $poinItem : 0.0,
                'waktu_jawab'      => now(),
            ]);
        }
    }

    // ── Utilitas ──────────────────────────────────────────────────────────────

    /**
     * Hitung skor per tipe sebagai persentase (0–100), konsisten dengan ScoringService.
     * skor_total = rata-rata tipe yang ada.
     *
     * @return array{0: float|null, 1: float|null, 2: float|null, 3: float|null, 4: float}
     */
    private function hitungSkor(
        int $pgB, int $pgT,
        int $bsB, int $bsT,
        int $lbB, int $lbT,
        int $mjB, int $mjT
    ): array {
        $skorPg = $pgT > 0 ? round($pgB / $pgT * 100, 4) : null;
        $skorBs = $bsT > 0 ? round($bsB / $bsT * 100, 4) : null;
        $skorLb = $lbT > 0 ? round($lbB / $lbT * 100, 4) : null;
        $skorMj = $mjT > 0 ? round($mjB / $mjT * 100, 4) : null;

        $tipaAda   = array_filter([$skorPg, $skorBs, $skorLb, $skorMj], fn ($v) => $v !== null);
        $skorTotal = count($tipaAda) > 0
            ? round(array_sum($tipaAda) / count($tipaAda), 4)
            : 0.0;

        return [$skorPg, $skorBs, $skorLb, $skorMj, $skorTotal];
    }

    /**
     * Tentukan benar/salah secara deterministik.
     * Menggunakan seed dari no_agenda + soal_id agar hasilnya sama setiap seeder dijalankan.
     */
    private function deterministikBenar(string $noAgenda, int $soalId, float $rate): bool
    {
        $seed = abs(crc32("{$noAgenda}-{$soalId}")) % 100;

        return $seed < (int) ($rate * 100);
    }

    /**
     * Ambil soal jadwal beserta opsinya (eager loaded, diurutkan nomor_urut).
     *
     * @return Collection<int, Soal>
     */
    private function getSoalJadwal(int $jadwalId): Collection
    {
        return Soal::whereHas('jadwalSoal', fn ($q) => $q->where('jadwal_ujian_id', $jadwalId))
            ->with(['opsi', 'jadwalSoal' => fn ($q) => $q->where('jadwal_ujian_id', $jadwalId)])
            ->get()
            ->sortBy(fn ($s) => $s->jadwalSoal->first()?->nomor_urut ?? 99)
            ->values();
    }

    /**
     * Buat catatan aktivitas pelanggaran di sesi_aktivitas.
     *
     * @param  array<int, array{jenis: string, menit_lalu: int}>  $aktivitas
     */
    private function buatAktivitasPelanggaran(int $sesiId, array $aktivitas): void
    {
        foreach ($aktivitas as $item) {
            DB::table('sesi_aktivitas')->insert([
                'sesi_ujian_id' => $sesiId,
                'jenis'         => $item['jenis'],
                'metadata'      => null,
                'created_at'    => now()->subMinutes($item['menit_lalu']),
            ]);
        }
    }
}
