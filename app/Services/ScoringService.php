<?php

namespace App\Services;

use App\Enums\StatusSesi;
use App\Enums\TipeSoal;
use App\Models\JadwalSoal;
use App\Models\Jawaban;
use App\Models\SesiUjian;
use App\Models\Soal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScoringService
{
    /**
     * Jalur penutup sesi TUNGGAL: score + snapshot skor + set status akhir,
     * dalam satu transaksi. Dipakai oleh selesai(), heartbeat kadaluarsa,
     * dan scheduler AutoSubmitSesi agar tidak ada jalur penutup tanpa nilai (F-01).
     *
     * @return array{skor_total: float, is_lulus: bool}
     */
    public function finalisasi(SesiUjian $sesi, StatusSesi $statusAkhir): array
    {
        $result = $this->score($sesi);

        DB::transaction(function () use ($sesi, $statusAkhir, $result): void {
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
            $sesi->status = $statusAkhir;
            // Kadaluarsa: waktu_selesai = waktu_batas (akurat), bukan now()
            $sesi->waktu_selesai = $statusAkhir === StatusSesi::Kadaluarsa
                ? ($sesi->waktu_batas ?? now())
                : now();
            $sesi->save();
        });

        return ['skor_total' => $result['skor_total'], 'is_lulus' => $result['is_lulus']];
    }

    /**
     * Hitung skor sesi secara deterministik.
     *
     * @return array{
     *   skor_pg: float|null,
     *   skor_benar_salah: float|null,
     *   skor_labeling: float|null,
     *   skor_menjodohkan: float|null,
     *   skor_total: float,
     *   is_lulus: bool,
     *   jawaban_updates: array<int, array{is_benar: bool, poin_didapat: float}>
     * }
     */
    public function score(SesiUjian $sesi): array
    {
        $jadwalId = $sesi->jadwal_ujian_id;
        $passingGrade = (float) ($sesi->jadwalUjian()->value('passing_grade') ?? 0);

        // Load semua soal di jadwal beserta opsi
        $jadwalSoalList = JadwalSoal::where('jadwal_ujian_id', $jadwalId)
            ->with(['soal.opsi'])
            ->get();

        // Load semua jawaban peserta dalam sesi — urut id agar pemilihan
        // "baris terbaru" untuk tipe single-answer deterministik (F-03)
        $jawabanList = Jawaban::where('sesi_ujian_id', $sesi->id)
            ->orderBy('id')
            ->get()
            ->keyBy(fn (Jawaban $j) => $this->jawabanKey($j));

        // Kelompokkan soal per tipe
        /** @var array<string, Collection<int, Soal>> */
        $soalPerTipe = [];
        foreach ($jadwalSoalList as $js) {
            if (! $js->soal) {
                continue;
            }
            $tipe = $js->soal->tipe->value;
            $soalPerTipe[$tipe][] = $js->soal;
        }

        // jawaban_updates: soal_id → [is_benar, poin_didapat]
        $jawabanUpdates = [];

        // Hitung skor per tipe
        $skorTipe = [];

        foreach ($soalPerTipe as $tipe => $soals) {
            $benar = 0;
            $total = count($soals);

            foreach ($soals as $soal) {
                [$isBenar, $poin] = $this->evaluasiSoal($soal, $jawabanList, $tipe);

                // Simpan per soal_id (bisa multi-row untuk labeling/menjodohkan)
                $jawabanUpdates[$soal->id] = [
                    'is_benar' => $isBenar,
                    'poin_didapat' => $poin,
                ];

                if ($isBenar) {
                    $benar++;
                }
            }

            // Skor tipe = (benar / total) × 100
            $skorTipe[$tipe] = $total > 0 ? round($benar / $total * 100, 4) : 0.0;
        }

        $skor_pg = isset($skorTipe[TipeSoal::Pg->value]) ? $skorTipe[TipeSoal::Pg->value] : null;
        $skor_benar_salah = isset($skorTipe[TipeSoal::BenarSalah->value]) ? $skorTipe[TipeSoal::BenarSalah->value] : null;
        $skor_labeling = isset($skorTipe[TipeSoal::Labeling->value]) ? $skorTipe[TipeSoal::Labeling->value] : null;
        $skor_menjodohkan = isset($skorTipe[TipeSoal::Menjodohkan->value]) ? $skorTipe[TipeSoal::Menjodohkan->value] : null;

        // Skor total = rata-rata tipe yang ada (tipe tidak ada di jadwal di-skip, bukan 0)
        $tipaAda = array_filter([$skor_pg, $skor_benar_salah, $skor_labeling, $skor_menjodohkan], fn ($v) => $v !== null);
        $skor_total = count($tipaAda) > 0 ? round(array_sum($tipaAda) / count($tipaAda), 4) : 0.0;

        return [
            'skor_pg' => $skor_pg,
            'skor_benar_salah' => $skor_benar_salah,
            'skor_labeling' => $skor_labeling,
            'skor_menjodohkan' => $skor_menjodohkan,
            'skor_total' => $skor_total,
            'is_lulus' => $skor_total >= $passingGrade,
            'jawaban_updates' => $jawabanUpdates,
        ];
    }

    /**
     * Evaluasi satu soal.
     *
     * @param  Collection<string, Jawaban>  $jawabanList
     * @return array{0: bool, 1: float}
     */
    private function evaluasiSoal(Soal $soal, Collection $jawabanList, string $tipe): array
    {
        return match ($tipe) {
            TipeSoal::Pg->value => $this->evaluasiPg($soal, $jawabanList),
            TipeSoal::BenarSalah->value => $this->evaluasiBenarSalah($soal, $jawabanList),
            TipeSoal::Labeling->value => $this->evaluasiLabeling($soal, $jawabanList),
            TipeSoal::Menjodohkan->value => $this->evaluasiMenjodohkan($soal, $jawabanList),
            default => [false, 0.0],
        };
    }

    /**
     * @param  Collection<string, Jawaban>  $jawabanList
     * @return array{0: bool, 1: float}
     */
    private function evaluasiPg(Soal $soal, Collection $jawabanList): array
    {
        $kunciOpsiIds = $soal->opsi->where('is_kunci', true)->pluck('id');
        if ($kunciOpsiIds->isEmpty()) {
            return [false, 0.0];
        }

        // Jawaban PG: satu opsi dipilih. Ambil baris TERBARU — baris lama bisa
        // tersisa dari data sebelum kunci upsert diperbaiki (F-03).
        $kunci = $kunciOpsiIds->first();
        $jawaban = $jawabanList->filter(fn (Jawaban $j) => $j->soal_id === $soal->id)->sortByDesc('id')->first();

        $isBenar = $jawaban && $jawaban->opsi_id === $kunci;

        return [$isBenar, $isBenar ? (float) $soal->poin : 0.0];
    }

    /**
     * @param  Collection<string, Jawaban>  $jawabanList
     * @return array{0: bool, 1: float}
     */
    private function evaluasiBenarSalah(Soal $soal, Collection $jawabanList): array
    {
        $jawaban = $jawabanList->filter(fn (Jawaban $j) => $j->soal_id === $soal->id)->sortByDesc('id')->first();

        if (! $jawaban || $jawaban->jawaban_bool === null) {
            return [false, 0.0];
        }

        $isBenar = $jawaban->jawaban_bool === $soal->jawaban_benar_bool;

        return [$isBenar, $isBenar ? (float) $soal->poin : 0.0];
    }

    /**
     * Labeling: all-or-nothing — semua label harus cocok.
     * Cocok: jawaban.nomor_jawaban === opsi.nomor_urut untuk setiap opsi.
     *
     * @param  Collection<string, Jawaban>  $jawabanList
     * @return array{0: bool, 1: float}
     */
    private function evaluasiLabeling(Soal $soal, Collection $jawabanList): array
    {
        $opsiList = $soal->opsi;
        if ($opsiList->isEmpty()) {
            return [false, 0.0];
        }

        $jawabanSoal = $jawabanList->filter(fn (Jawaban $j) => $j->soal_id === $soal->id);

        // Setiap opsi harus punya jawaban dengan nomor_jawaban == opsi.nomor_urut
        foreach ($opsiList as $opsi) {
            $cocok = $jawabanSoal->contains(
                fn (Jawaban $j) => $j->opsi_id === $opsi->id && $j->nomor_jawaban === $opsi->nomor_urut
            );
            if (! $cocok) {
                return [false, 0.0];
            }
        }

        return [true, (float) $soal->poin];
    }

    /**
     * Menjodohkan: all-or-nothing — semua pasangan harus cocok.
     *
     * Skema: setiap OpsiSoal mewakili satu pasangan (teks=Kolom A, pasangan=Kolom B).
     * Jawaban peserta: opsi_id = opsi yang dijawab, pasangan_opsi_id = opsi yang dipilih
     * sebagai sumber jawaban Kolom B. Kunci benar: pasangan_opsi_id == opsi_id
     * (peserta memilih pasangan teks dari opsi itu sendiri).
     *
     * @param  Collection<string, Jawaban>  $jawabanList
     * @return array{0: bool, 1: float}
     */
    private function evaluasiMenjodohkan(Soal $soal, Collection $jawabanList): array
    {
        $opsiList = $soal->opsi;
        if ($opsiList->isEmpty()) {
            return [false, 0.0];
        }

        $jawabanSoal = $jawabanList->filter(fn (Jawaban $j) => $j->soal_id === $soal->id);

        foreach ($opsiList as $opsi) {
            $jawaban = $jawabanSoal->first(fn (Jawaban $j) => $j->opsi_id === $opsi->id);

            if (! $jawaban || $jawaban->pasangan_opsi_id !== $opsi->id) {
                return [false, 0.0];
            }
        }

        return [true, (float) $soal->poin];
    }

    private function jawabanKey(Jawaban $j): string
    {
        // Key unik untuk lookup — gunakan string gabungan
        return "{$j->soal_id}_{$j->opsi_id}_{$j->nomor_jawaban}_{$j->pasangan_opsi_id}";
    }
}
