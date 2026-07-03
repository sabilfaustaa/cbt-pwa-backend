<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Models\JadwalSoal;
use App\Models\JadwalUjian;
use App\Models\Jawaban;
use App\Models\SesiUjian;
use App\Services\RekapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HasilController
{
    // ─── GET /sesi/:id/hasil ────────────────────────────────────

    public function hasil(int $id, Request $request): JsonResponse
    {
        $sesi = SesiUjian::with(['jadwalUjian', 'user:id,name,nik'])->findOrFail($id);
        $user = $request->user();
        $role = $user->namaRole->value ?? '';

        $isPeserta = $role === 'peserta';
        $isPrivileged = in_array($role, ['pengawas', 'admin'], true);

        // Peserta hanya bisa akses sesi milik sendiri
        if ($isPeserta && $sesi->user_id !== $user->id) {
            throw new HttpException(403, 'Akses ditolak.');
        }

        // Gating: peserta hanya bisa lihat hasil jika sesi selesai DAN tampilkan_hasil=true
        $bolehLihat = $isPrivileged || (
            $sesi->status->value === 'selesai' &&
            (bool) $sesi->jadwalUjian?->tampilkan_hasil
        );

        if (! $bolehLihat) {
            throw new HttpException(403, 'Hasil ujian belum tersedia.');
        }

        // Ambil semua jawaban sesi, index by soal_id — urut id naik sehingga
        // keyBy menyisakan baris TERBARU, konsisten dengan ScoringService (F-03)
        $jawabanList = Jawaban::with('soal')
            ->where('sesi_ujian_id', $sesi->id)
            ->orderBy('id')
            ->get()
            ->keyBy('soal_id');

        $jadwalSoalList = JadwalSoal::with(['soal.opsi'])
            ->where('jadwal_ujian_id', $sesi->jadwal_ujian_id)
            ->orderBy('nomor_urut')
            ->get();

        // Hitung benar/total per tipe untuk detail_skor
        $counter = ['pg' => ['benar' => 0, 'total' => 0], 'benar_salah' => ['benar' => 0, 'total' => 0], 'labeling' => ['benar' => 0, 'total' => 0], 'menjodohkan' => ['benar' => 0, 'total' => 0]];
        foreach ($jadwalSoalList as $js) {
            $soal = $js->soal;
            if (! $soal) {
                continue;
            }
            $tipe = $soal->tipe->value;
            $counter[$tipe]['total']++;
            $jwb = $jawabanList->get($soal->id);
            if ($jwb && $jwb->is_benar === true) {
                $counter[$tipe]['benar']++;
            }
        }

        $skorMap = ['pg' => $sesi->skor_pg, 'benar_salah' => $sesi->skor_benar_salah, 'labeling' => $sesi->skor_labeling, 'menjodohkan' => $sesi->skor_menjodohkan];
        $detailSkor = [];
        foreach (array_keys($counter) as $t) {
            $total = $counter[$t]['total'];
            $detailSkor[$t] = $total > 0
                ? ['benar' => $counter[$t]['benar'], 'total' => $total, 'skor' => (float) ($skorMap[$t] ?? 0)]
                : null;
        }

        // Sesi shape sesuai FE SesiUjian type — sertakan skor & jumlah_pelanggaran
        $sesiData = [
            'id' => $sesi->id,
            'jadwal_ujian_id' => $sesi->jadwal_ujian_id,
            'user_id' => $sesi->user_id,
            'waktu_mulai' => $sesi->waktu_mulai?->toIso8601String(),
            'waktu_batas' => $sesi->waktu_batas?->toIso8601String(),
            'waktu_selesai' => $sesi->waktu_selesai?->toIso8601String(),
            'status' => $sesi->status->value,
            'skor_pg' => $sesi->skor_pg,
            'skor_benar_salah' => $sesi->skor_benar_salah,
            'skor_labeling' => $sesi->skor_labeling,
            'skor_menjodohkan' => $sesi->skor_menjodohkan,
            'skor_total' => $sesi->skor_total,
            'is_lulus' => $sesi->is_lulus,
            'ip_mulai' => $sesi->ip_mulai,
            'user_agent_mulai' => $sesi->user_agent_mulai,
            'jumlah_pelanggaran' => $sesi->jumlah_pelanggaran,
            'created_at' => $sesi->created_at?->toIso8601String(),
            'updated_at' => $sesi->updated_at?->toIso8601String(),
        ];

        // review_soal sesuai ReviewSoalItem FE type
        $reviewSoal = [];
        foreach ($jadwalSoalList as $js) {
            $soal = $js->soal;
            if (! $soal) {
                continue;
            }
            $tipe = $soal->tipe->value;
            $jwb = $jawabanList->get($soal->id);

            // SoalPublic — tanpa kunci
            $soalPublic = [
                'id' => $soal->id,
                'tipe' => $tipe,
                'pertanyaan' => $soal->pertanyaan,
                'media_url' => $soal->media_url,
                'poin' => (int) $soal->poin,
                'nomor_urut' => (int) $js->nomor_urut,
            ];
            if (in_array($tipe, ['pg', 'labeling', 'menjodohkan'], true)) {
                $soalPublic['opsi'] = $soal->opsi->map(fn ($o) => array_filter([
                    'id' => $o->id,
                    'teks' => $o->teks,
                    'pasangan' => $tipe === 'menjodohkan' ? $o->pasangan : null,
                ], fn ($v) => $v !== null))->values()->all();
            }

            // jawaban_peserta — single {opsi_id, jawaban_bool}
            $jawabanPeserta = ['opsi_id' => $jwb?->opsi_id, 'jawaban_bool' => $jwb?->jawaban_bool];

            // kunci_jawaban
            $kunciOpsiId = $tipe === 'pg' ? ($soal->opsi->firstWhere('is_kunci', true)?->id) : null;
            $kunciBool = $tipe === 'benar_salah' ? $soal->jawaban_benar_bool : null;

            $reviewSoal[] = [
                'soal' => $soalPublic,
                'jawaban_peserta' => $jawabanPeserta,
                'kunci_jawaban' => ['opsi_id' => $kunciOpsiId, 'jawaban_bool' => $kunciBool],
                'is_benar' => $jwb !== null && $jwb->is_benar === true,
                'pembahasan' => $soal->pembahasan,
            ];
        }

        // Blok jadwal untuk komponen sertifikat FE (M-B3)
        $jadwalData = $sesi->jadwalUjian ? [
            'nama_ujian' => $sesi->jadwalUjian->nama_ujian,
            'kode_jadwal' => $sesi->jadwalUjian->kode_jadwal,
            'passing_grade' => (int) $sesi->jadwalUjian->passing_grade,
            'instansi' => config('app.instansi', 'CBT Mandiri'),
        ] : null;

        return ApiResponse::success([
            'sesi' => $sesiData,
            'detail_skor' => $detailSkor,
            'review_soal' => $reviewSoal,
            'jadwal' => $jadwalData,
        ], 'Hasil ujian.');
    }

    // ─── GET /jadwal-ujian/:id/rekap ────────────────────────────

    public function rekap(int $id, RekapService $rekapService): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);
        $data = $rekapService->rekap($jadwal);

        return ApiResponse::success($data, 'Rekap jadwal ujian.');
    }

    // ─── GET /jadwal-ujian/:id/export ───────────────────────────

    public function export(int $id, Request $request): Response
    {
        $jadwal = JadwalUjian::with('sesiUjian.user:id,name,nik')->findOrFail($id);
        $format = $request->query('format', 'csv');

        if (! in_array($format, ['csv', 'xlsx'], true)) {
            throw new HttpException(422, 'Format tidak didukung. Gunakan csv atau xlsx.');
        }

        $sesiList = $jadwal->sesiUjian->where('status.value', 'selesai')->sortBy('user.name');

        $filename = "rekap-{$jadwal->kode_jadwal}.{$format}";

        if ($format === 'csv') {
            return $this->exportCsv($sesiList, $filename);
        }

        // Keputusan (M-B7 opsi A): xlsx di-serve sebagai CSV-kompatibel — nol dependensi
        // eksternal (maatwebsite/excel tidak dipasang). File CSV terbuka langsung di Excel.
        return $this->exportCsv($sesiList, "rekap-{$jadwal->kode_jadwal}.csv");
    }

    /** @param Collection<array-key, SesiUjian> $sesiList */
    private function exportCsv(Collection $sesiList, string $filename): Response
    {
        $lines = [];
        $lines[] = implode(',', [
            '"No"', '"Nama"', '"NIK"', '"Status"',
            '"Skor PG"', '"Skor Benar-Salah"', '"Skor Labeling"', '"Skor Menjodohkan"',
            '"Skor Total"', '"Lulus"',
        ]);

        $no = 1;
        foreach ($sesiList as $sesi) {
            $lines[] = implode(',', [
                $no++,
                '"'.($sesi->user->name ?? '').'"',
                '"'.($sesi->user->nik ?? '').'"',
                '"'.$sesi->status->value.'"',
                $sesi->skor_pg ?? '',
                $sesi->skor_benar_salah ?? '',
                $sesi->skor_labeling ?? '',
                $sesi->skor_menjodohkan ?? '',
                $sesi->skor_total ?? '',
                $sesi->is_lulus ? 'Ya' : 'Tidak',
            ]);
        }

        $content = implode("\n", $lines);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
