<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusSesi;
use App\Enums\TipeSoal;
use App\Helpers\ApiResponse;
use App\Http\Requests\Sesi\AktivitasRequest;
use App\Http\Requests\Sesi\JawabanRequest;
use App\Http\Requests\Sesi\MulaiSesiRequest;
use App\Http\Resources\JawabanResource;
use App\Http\Resources\SesiResource;
use App\Http\Resources\SoalPublicResource;
use App\Models\JadwalPeserta;
use App\Models\JadwalSoal;
use App\Models\Jawaban;
use App\Models\SesiAktivitas;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Services\ScoringService;
use App\Services\SesiService;
use App\Services\ShuffleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SesiController
{
    public function __construct(
        private SesiService $sesiService,
        private ScoringService $scoringService,
    ) {}

    // ─── GET /sesi/saya ────────────────────────────────────────

    public function saya(Request $request): JsonResponse
    {
        $user = $request->user();

        $sesiList = SesiUjian::with([
            'jadwalUjian' => fn ($q) => $q->withTrashed(),
        ])
            ->where('user_id', $user->id)
            ->get();

        $tokens = JadwalPeserta::where('user_id', $user->id)
            ->pluck('token_akses', 'jadwal_ujian_id');

        $jadwalAktif = [];
        $riwayat = [];

        foreach ($sesiList as $sesi) {
            $jadwal = $sesi->jadwalUjian;
            if (! $jadwal) {
                continue;
            }

            $statusStr = $sesi->status->value;

            $item = [
                'sesi_id' => $sesi->id,
                'jadwal' => [
                    'id' => $jadwal->id,
                    'nama_ujian' => $jadwal->nama_ujian,
                    'kode_jadwal' => $jadwal->kode_jadwal,
                    'waktu_mulai' => $jadwal->waktu_mulai?->toIso8601String(),
                    'durasi_menit' => $jadwal->durasi_menit,
                    'status' => $jadwal->status->value,
                ],
                'status' => $statusStr,
                'token_akses' => $tokens[$jadwal->id] ?? '',
                'skor_total' => $sesi->skor_total,
                'is_lulus' => $sesi->is_lulus,
            ];

            if (in_array($statusStr, ['belum_mulai', 'sedang_berlangsung'], true)) {
                $jadwalAktif[] = $item;
            } else {
                $riwayat[] = $item;
            }
        }

        return ApiResponse::success([
            'jadwal_aktif' => $jadwalAktif,
            'riwayat' => $riwayat,
        ], 'Daftar ujian.');
    }

    // ─── POST /sesi/mulai ──────────────────────────────────────

    public function mulai(MulaiSesiRequest $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->input('token_akses');

        $jadwalPeserta = $this->sesiService->validateToken($user, $token);
        $sesi = $this->sesiService->mulaiSesi(
            $jadwalPeserta,
            (string) $request->ip(),
            (string) $request->userAgent(),
            (bool) $request->boolean('persetujuan'),
        );

        $sisaDetik = $this->sesiService->sisaDetik($sesi);
        $soalCount = $this->sesiService->soalCount($sesi);

        return ApiResponse::success([
            'sesi' => [
                'id' => $sesi->id,
                'status' => $sesi->status->value,
                'waktu_mulai' => $sesi->waktu_mulai?->toIso8601String(),
                'waktu_batas' => $sesi->waktu_batas?->toIso8601String(),
            ],
            'sisa_detik' => $sisaDetik,
            'soal_count' => $soalCount,
            'server_time' => now()->toIso8601String(),
        ], 'Sesi dimulai.');
    }

    // ─── GET /sesi/:id/soal ────────────────────────────────────

    public function soal(int $id, Request $request, ShuffleService $shuffleService): JsonResponse
    {
        $sesi = SesiUjian::findOrFail($id);
        if ($sesi->user_id !== $request->user()->id) {
            throw new HttpException(403, 'Akses ditolak. Sesi ini bukan milik Anda.');
        }

        $this->pastikanSesiBerlangsung($sesi);

        $jadwal = $sesi->jadwalUjian()->first();
        if (! $jadwal) {
            return ApiResponse::error('Jadwal ujian tidak ditemukan.', 404);
        }

        $jadwalSoalList = JadwalSoal::where('jadwal_ujian_id', $jadwal->id)
            ->with(['soal.opsi'])
            ->orderBy('nomor_urut')
            ->get();

        $seed = $shuffleService->makeSeed($sesi->user_id, $sesi->id);

        if ($jadwal->acak_soal) {
            $jadwalSoalList = collect($shuffleService->shuffle($jadwalSoalList->all(), $seed));
        }

        $soalOutput = [];
        $nomor = 1;

        foreach ($jadwalSoalList as $js) {
            $soal = $js->soal;
            if (! $soal) {
                continue;
            }

            $resource = new SoalPublicResource($soal);
            $resource->nomorUrut = $nomor++;

            $opsi = $soal->opsi;

            if ($jadwal->acak_opsi && $opsi->isNotEmpty()) {
                $opsiArr = $shuffleService->shuffle($opsi->all(), $seed + $soal->id);
                $opsi = collect($opsiArr);
            }

            $resource->shuffledOpsi = $opsi->values();

            if ($soal->tipe === TipeSoal::Menjodohkan && $opsi->isNotEmpty()) {
                $pasanganArr = [];
                foreach ($opsi as $o) {
                    $pasanganArr[] = (object) ['teks' => $o->pasangan];
                }
                $resource->shuffledPasangan = collect($shuffleService->shuffle($pasanganArr, $seed + $soal->id + 1000));
            }

            $soalOutput[] = $resource;
        }

        $jawabanTersimpan = Jawaban::where('sesi_ujian_id', $sesi->id)
            ->get()
            ->map(fn (Jawaban $j): array => [
                'soal_id' => $j->soal_id,
                'opsi_id' => $j->opsi_id,
                'jawaban_bool' => $j->jawaban_bool,
                'nomor_jawaban' => $j->nomor_jawaban,
                'pasangan_opsi_id' => $j->pasangan_opsi_id,
            ]);

        $sisaDetik = $this->sesiService->sisaDetik($sesi);

        return ApiResponse::success([
            'sesi' => (new SesiResource($sesi))->resolve(),
            'sisa_detik' => $sisaDetik,
            'server_time' => now()->toIso8601String(),
            'soal' => collect($soalOutput)->map(fn (SoalPublicResource $r) => $r->resolve())->values(),
            'jawaban_tersimpan' => $jawabanTersimpan->values(),
        ], 'Daftar soal.');
    }

    // ─── GET /sesi/:id/soal/:soalId ────────────────────────────

    public function soalSatuan(int $id, int $soalId, Request $request): JsonResponse
    {
        $sesi = SesiUjian::findOrFail($id);
        if ($sesi->user_id !== $request->user()->id) {
            throw new HttpException(403, 'Akses ditolak. Sesi ini bukan milik Anda.');
        }

        $this->pastikanSesiBerlangsung($sesi);

        $jadwal = $sesi->jadwalUjian()->first();
        if (! $jadwal) {
            return ApiResponse::error('Jadwal ujian tidak ditemukan.', 404);
        }

        $jadwalSoal = JadwalSoal::where('jadwal_ujian_id', $jadwal->id)
            ->where('soal_id', $soalId)
            ->first();

        if (! $jadwalSoal) {
            return ApiResponse::error('Soal tidak ditemukan di jadwal ini.', 404);
        }

        $soal = Soal::with('opsi')->findOrFail($soalId);
        $shuffleService = app(ShuffleService::class);

        $resource = new SoalPublicResource($soal);
        $resource->nomorUrut = $jadwalSoal->nomor_urut ?? 0;

        $opsi = $soal->opsi;

        if ($jadwal->acak_opsi && $opsi->isNotEmpty()) {
            $seed = $shuffleService->makeSeed($sesi->user_id, $sesi->id);
            $opsiArr = $shuffleService->shuffle($opsi->all(), $seed + $soal->id);
            $opsi = collect($opsiArr);
            $resource->shuffledOpsi = $opsi;

            if ($soal->tipe->value === TipeSoal::Menjodohkan->value) {
                $pasanganArr = [];
                foreach ($opsi as $o) {
                    $pasanganArr[] = (object) ['teks' => $o->pasangan];
                }
                $resource->shuffledPasangan = collect($shuffleService->shuffle($pasanganArr, $seed + $soal->id + 1000));
            }
        } elseif ($soal->tipe->value === TipeSoal::Menjodohkan->value) {
            $resource->shuffledOpsi = $opsi;
        } else {
            $resource->shuffledOpsi = $opsi;
        }

        $jawaban = Jawaban::where('sesi_ujian_id', $sesi->id)
            ->where('soal_id', $soalId)
            ->first();

        $jawabanSaatIni = null;
        if ($jawaban) {
            $jawabanSaatIni = [
                'soal_id' => $jawaban->soal_id,
                'opsi_id' => $jawaban->opsi_id,
                'jawaban_bool' => $jawaban->jawaban_bool,
                'nomor_jawaban' => $jawaban->nomor_jawaban,
                'pasangan_opsi_id' => $jawaban->pasangan_opsi_id,
                'waktu_jawab' => $jawaban->waktu_jawab?->toIso8601String(),
            ];
        }

        return ApiResponse::success([
            'soal' => $resource->resolve(),
            'jawaban_saat_ini' => $jawabanSaatIni,
        ], 'Detail soal.');
    }

    // ─── PUT /sesi/:id/jawaban ──────────────────────────────────

    public function jawaban(int $id, JawabanRequest $request): JsonResponse
    {
        $sesi = $request->getSesi();
        $soal = $request->getSoal();

        // Terima kedua nama header: Versi A (manual) mengirim `X-Idempotency-Key`,
        // sedangkan useBackgroundSync library (Versi B) mengirim `Idempotency-Key`.
        // Dedupe TC-CBT-04 harus lulus di kedua versi. (KT-2)
        $idempotencyKey = $request->header('X-Idempotency-Key')
            ?? $request->header('Idempotency-Key');

        $jawaban = $this->sesiService->upsertJawaban(
            $sesi,
            $soal,
            $request->input('opsi_id') ? (int) $request->input('opsi_id') : null,
            $request->has('jawaban_bool') ? (bool) $request->input('jawaban_bool') : null,
            $request->input('nomor_jawaban') ? (int) $request->input('nomor_jawaban') : null,
            $request->input('pasangan_opsi_id') ? (int) $request->input('pasangan_opsi_id') : null,
            $idempotencyKey ? (string) $idempotencyKey : null,
        );

        $sisaDetik = $this->sesiService->sisaDetik($sesi);

        return ApiResponse::success([
            'jawaban' => (new JawabanResource($jawaban))->resolve(),
            'sisa_detik' => $sisaDetik,
            'server_time' => now()->toIso8601String(),
        ], 'Jawaban disimpan.');
    }

    // ─── GET /sesi/:id/heartbeat ────────────────────────────────

    public function heartbeat(int $id, Request $request): JsonResponse
    {
        $sesi = SesiUjian::findOrFail($id);

        if ($sesi->user_id !== $request->user()->id) {
            throw new HttpException(403, 'Akses ditolak.');
        }

        $status = $sesi->status->value;
        $sisaDetik = 0;

        if ($status === 'sedang_berlangsung' && $sesi->waktu_batas && now()->gte($sesi->waktu_batas)) {
            // F-01: heartbeat adalah jalur penutup yang paling sering menang atas
            // scheduler (polling 15 dtk vs 60 dtk) — WAJIB men-score, bukan hanya
            // menandai kadaluarsa. Bila scoring gagal, biarkan status apa adanya
            // agar scheduler AutoSubmitSesi me-retry pada run berikutnya.
            try {
                $this->scoringService->finalisasi($sesi, StatusSesi::Kadaluarsa);
                $status = 'kadaluarsa';
            } catch (\Throwable $e) {
                Log::error("Heartbeat: gagal finalisasi sesi #{$sesi->id}", ['error' => $e->getMessage()]);
            }
        } else {
            $sisaDetik = $this->sesiService->sisaDetik($sesi);
        }

        return ApiResponse::success([
            'sisa_detik' => $sisaDetik,
            'status' => $status,
            'server_time' => now()->toIso8601String(),
            'waktu_batas' => $sesi->waktu_batas?->toIso8601String(),
        ], 'Heartbeat OK.');
    }

    // ─── POST /sesi/:id/selesai ─────────────────────────────────

    public function selesai(int $id, Request $request): JsonResponse
    {
        $sesi = SesiUjian::with('jadwalUjian')->findOrFail($id);

        if ($sesi->user_id !== $request->user()->id) {
            throw new HttpException(403, 'Akses ditolak. Sesi ini bukan milik Anda.');
        }

        // Idempotent: jika sudah selesai/kadaluarsa, kembalikan state saat ini tanpa re-score.
        // Safety-net F-01: sesi kadaluarsa yang tersangkut TANPA skor (ditutup jalur
        // lama yang tidak men-score) dinilai dulu sebelum dikembalikan.
        if (in_array($sesi->status->value, ['selesai', 'kadaluarsa'], true)) {
            if ($sesi->status->value === 'kadaluarsa' && $sesi->skor_total === null) {
                $this->scoringService->finalisasi($sesi, StatusSesi::Kadaluarsa);
                $sesi->refresh();
            }

            $tampilkanHasil = (bool) $sesi->jadwalUjian?->tampilkan_hasil;

            return ApiResponse::success([
                'sesi' => (new SesiResource($sesi))->resolve(),
                'tampilkan_hasil' => $tampilkanHasil,
            ], 'Ujian sudah diselesaikan sebelumnya.');
        }

        if ($sesi->status->value !== 'sedang_berlangsung') {
            throw new HttpException(409, 'Sesi tidak bisa diselesaikan. Status saat ini: '.$sesi->status->value.'.');
        }

        // Validasi deadline: jika submit setelah waktu habis, tandai sebagai kadaluarsa
        $sudahKadaluarsa = $sesi->waktu_batas && now()->gte($sesi->waktu_batas);
        $statusAkhir = $sudahKadaluarsa ? StatusSesi::Kadaluarsa : StatusSesi::Selesai;

        $this->scoringService->finalisasi($sesi, $statusAkhir);

        $sesi->refresh();
        $tampilkanHasil = (bool) $sesi->jadwalUjian?->tampilkan_hasil;

        return ApiResponse::success([
            'sesi' => (new SesiResource($sesi))->resolve(),
            'tampilkan_hasil' => $tampilkanHasil,
        ], 'Ujian berhasil diselesaikan.');
    }

    // ─── POST /sesi/:id/aktivitas ───────────────────────────────

    public function aktivitas(int $id, AktivitasRequest $request): JsonResponse
    {
        $sesi = SesiUjian::findOrFail($id);

        if ($sesi->user_id !== $request->user()->id) {
            throw new HttpException(403, 'Akses ditolak.');
        }

        SesiAktivitas::create([
            'sesi_ujian_id' => $sesi->id,
            'jenis' => $request->input('jenis'),
            'metadata' => $request->input('metadata'),
            'waktu_kejadian' => now(),
        ]);

        $sesi->increment('jumlah_pelanggaran');

        return ApiResponse::success([], 'Aktivitas tercatat.', 201);
    }

    // ─── Gate akses soal (F-02) ─────────────────────────────────
    // Soal hanya boleh dibaca saat sesi sedang_berlangsung. Sesi belum_mulai
    // (termasuk jadwal yang belum dibuka) tidak boleh mengunduh materi ujian;
    // sesi selesai/kadaluarsa/dibatalkan meninjau lewat GET /sesi/{id}/hasil
    // yang punya gating tampilkan_hasil sendiri.
    private function pastikanSesiBerlangsung(SesiUjian $sesi): void
    {
        $status = $sesi->status->value;

        if ($status === 'sedang_berlangsung') {
            return;
        }

        if ($status === 'belum_mulai') {
            throw new HttpException(409, 'Sesi belum dimulai. Mulai ujian terlebih dahulu.');
        }

        throw new HttpException(409, 'Sesi sudah berakhir. Gunakan halaman hasil untuk meninjau jawaban.');
    }
}
