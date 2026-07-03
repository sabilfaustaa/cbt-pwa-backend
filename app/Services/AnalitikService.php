<?php

namespace App\Services;

use App\Enums\StatusSesi;
use App\Enums\TipeSoal;
use App\Models\JadwalSoal;
use App\Models\JadwalUjian;
use App\Models\Jawaban;
use App\Models\SesiUjian;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Analitik butir soal pasca-ujian: agregat skor + statistik tiap butir
 * (p-value, daya beda kelompok atas/bawah 27%, distraktor untuk PG).
 *
 * TODO: untuk dataset >500 peserta, pertimbangkan cache per-jadwal
 * (saat ini in-memory cukup untuk skala skripsi ≤50 peserta).
 */
class AnalitikService
{
    /**
     * @return array{agregat: array<string, mixed>, butir: array<int, array<string, mixed>>}
     */
    public function analitik(JadwalUjian $jadwal): array
    {
        $totalPeserta = $jadwal->jadwalPeserta()->count();

        /** @var Collection<int, SesiUjian> $sesiSelesai */
        $sesiSelesai = SesiUjian::where('jadwal_ujian_id', $jadwal->id)
            ->where('status', StatusSesi::Selesai)
            ->get();

        $sesiIds = $sesiSelesai->pluck('id')->all();

        /** @var Collection<int, float> $skorList */
        $skorList = $sesiSelesai->pluck('skor_total')
            ->filter(fn ($v) => $v !== null)
            ->map(fn ($v) => (float) $v)
            ->values();

        $agregat = [
            'total_peserta' => $totalPeserta,
            'selesai' => $sesiSelesai->count(),
            'rata_rata' => $skorList->isNotEmpty() ? round((float) $skorList->avg(), 1) : 0.0,
            'median' => $skorList->isNotEmpty() ? round($this->median($skorList->all()), 1) : 0.0,
            'skor_min' => $skorList->isNotEmpty() ? (int) round((float) $skorList->min()) : 0,
            'skor_max' => $skorList->isNotEmpty() ? (int) round((float) $skorList->max()) : 0,
            'lulus' => $sesiSelesai->where('is_lulus', true)->count(),
            'distribusi_skor' => $this->distribusiSkor($skorList->all()),
        ];

        // Kelompok atas/bawah 27% berdasarkan skor_total (untuk daya beda)
        $ranked = $sesiSelesai->sortByDesc('skor_total')->values();
        $n = $ranked->count();
        $groupSize = (int) floor($n * 0.27);
        $atasIds = $groupSize > 0 ? $ranked->take($groupSize)->pluck('id')->all() : [];
        $bawahIds = $groupSize > 0 ? $ranked->slice($n - $groupSize)->pluck('id')->all() : [];

        // Semua jawaban sesi selesai, dikelompokkan per soal lalu per sesi
        $jawabanBySoal = empty($sesiIds)
            ? collect()
            : Jawaban::whereIn('sesi_ujian_id', $sesiIds)->get()->groupBy('soal_id');

        $jadwalSoalList = JadwalSoal::with(['soal.opsi'])
            ->where('jadwal_ujian_id', $jadwal->id)
            ->orderBy('nomor_urut')
            ->get();

        $butir = [];
        foreach ($jadwalSoalList as $js) {
            $soal = $js->soal;
            if (! $soal) {
                continue;
            }

            $tipe = $soal->tipe->value;

            /** @var Collection<int, Jawaban> $jawabanSoal */
            $jawabanSoal = $jawabanBySoal->get($soal->id, collect());
            $bySesi = $jawabanSoal->groupBy('sesi_ujian_id');

            // p-value: benar / yang menjawab (di antara peserta selesai)
            $menjawab = 0;
            $benar = 0;
            foreach ($sesiIds as $sid) {
                $rows = $bySesi->get($sid);
                if ($rows && $rows->isNotEmpty()) {
                    $menjawab++;
                    if ($this->sesiBenar($rows)) {
                        $benar++;
                    }
                }
            }
            $pValue = $menjawab > 0 ? round($benar / $menjawab, 2) : 0.0;

            // Daya beda: proporsi benar kelompok atas - kelompok bawah (denominator = ukuran kelompok)
            $dayaBeda = 0.0;
            if ($groupSize > 0) {
                $pAtas = $this->proporsiBenar($atasIds, $bySesi) / $groupSize;
                $pBawah = $this->proporsiBenar($bawahIds, $bySesi) / $groupSize;
                $dayaBeda = round($pAtas - $pBawah, 2);
            }

            $item = [
                'soal_id' => $soal->id,
                'nomor_urut' => (int) $js->nomor_urut,
                'tipe' => $tipe,
                'stem_ringkas' => Str::limit((string) $soal->pertanyaan, 80),
                'p_value' => $pValue,
                'daya_beda' => $dayaBeda,
            ];

            // Distraktor khusus PG
            if ($tipe === TipeSoal::Pg->value) {
                $totalPilih = $jawabanSoal->whereNotNull('opsi_id')->count();
                $distraktor = [];
                foreach ($soal->opsi as $i => $opsi) {
                    $jumlah = $jawabanSoal->where('opsi_id', $opsi->id)->count();
                    $distraktor[] = [
                        'opsi_label' => $this->opsiLabel($i),
                        'jumlah_pilih' => $jumlah,
                        'pct' => $totalPilih > 0 ? (int) round($jumlah / $totalPilih * 100) : 0,
                    ];
                }
                $item['distraktor'] = $distraktor;
            }

            $butir[] = $item;
        }

        return [
            'agregat' => $agregat,
            'butir' => $butir,
        ];
    }

    /**
     * Sebuah butir dianggap benar untuk satu sesi bila SEMUA baris jawaban
     * untuk soal tsb is_benar=true (mendukung tipe multi-baris labeling/menjodohkan).
     *
     * @param  Collection<int, Jawaban>  $rows
     */
    private function sesiBenar(Collection $rows): bool
    {
        return $rows->isNotEmpty() && $rows->every(fn (Jawaban $j) => $j->is_benar === true);
    }

    /**
     * Hitung jumlah peserta (dari daftar sesi) yang menjawab butir dengan benar.
     *
     * @param  array<int, int>  $sesiIds
     * @param  Collection<int, Collection<int, Jawaban>>  $bySesi
     */
    private function proporsiBenar(array $sesiIds, Collection $bySesi): int
    {
        $count = 0;
        foreach ($sesiIds as $sid) {
            $rows = $bySesi->get($sid);
            if ($rows && $this->sesiBenar($rows)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<int, float>  $skor
     */
    private function median(array $skor): float
    {
        if (empty($skor)) {
            return 0.0;
        }

        sort($skor);
        $count = count($skor);
        $mid = intdiv($count, 2);

        return $count % 2 === 0
            ? ($skor[$mid - 1] + $skor[$mid]) / 2
            : $skor[$mid];
    }

    /**
     * @param  array<int, float>  $skor
     * @return array<int, array{rentang: string, jumlah: int}>
     */
    private function distribusiSkor(array $skor): array
    {
        $bins = [
            '<40' => 0, '40-49' => 0, '50-59' => 0, '60-69' => 0,
            '70-79' => 0, '80-89' => 0, '90-100' => 0,
        ];

        foreach ($skor as $s) {
            if ($s < 40) {
                $bins['<40']++;
            } elseif ($s < 50) {
                $bins['40-49']++;
            } elseif ($s < 60) {
                $bins['50-59']++;
            } elseif ($s < 70) {
                $bins['60-69']++;
            } elseif ($s < 80) {
                $bins['70-79']++;
            } elseif ($s < 90) {
                $bins['80-89']++;
            } else {
                $bins['90-100']++;
            }
        }

        $out = [];
        foreach ($bins as $rentang => $jumlah) {
            $out[] = ['rentang' => $rentang, 'jumlah' => $jumlah];
        }

        return $out;
    }

    private function opsiLabel(int $index): string
    {
        return chr(65 + $index); // 0→A, 1→B, ...
    }
}
