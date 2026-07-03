<?php

namespace App\Services;

use App\Models\JadwalUjian;
use App\Models\SesiUjian;

class RekapService
{
    /**
     * @return array{
     *   total_peserta: int,
     *   selesai: int,
     *   sedang_berlangsung: int,
     *   belum_mulai: int,
     *   lulus: int,
     *   tidak_lulus: int,
     *   rata_rata_skor: float|null,
     *   tertinggi: float|null,
     *   terendah: float|null,
     *   distribusi: array<string, int>,
     *   detail: array<int, array{nama: string, nik: string, no_agenda: string, skor_pg: float|null, skor_benar_salah: float|null, skor_labeling: float|null, skor_menjodohkan: float|null, skor_total: float|null, is_lulus: bool|null, waktu_selesai: string|null}>
     * }
     */
    public function rekap(JadwalUjian $jadwal): array
    {
        $sesiList = SesiUjian::with('user:id,name,nik,no_agenda')
            ->where('jadwal_ujian_id', $jadwal->id)
            ->get();

        $totalPeserta = $sesiList->count();
        $selesai = $sesiList->filter(fn ($s) => $s->status->value === 'selesai')->count();
        $sedangBerlangsung = $sesiList->filter(fn ($s) => $s->status->value === 'sedang_berlangsung')->count();
        $belumMulai = $sesiList->filter(fn ($s) => $s->status->value === 'belum_mulai')->count();

        $sesiSelesai = $sesiList->filter(fn ($s) => $s->status->value === 'selesai');
        $lulus = $sesiSelesai->where('is_lulus', true)->count();
        $tidakLulus = $sesiSelesai->where('is_lulus', false)->count();

        $skorList = $sesiSelesai->pluck('skor_total')->filter(fn ($v) => $v !== null);
        $rataSkor = $skorList->isNotEmpty() ? round($skorList->avg(), 2) : null;
        $tertinggi = $skorList->isNotEmpty() ? round((float) $skorList->max(), 2) : null;
        $terendah = $skorList->isNotEmpty() ? round((float) $skorList->min(), 2) : null;

        $detail = $sesiSelesai
            ->sortBy(fn ($s) => $s->user->name ?? '')
            ->values()
            ->map(fn ($s) => [
                'nama' => $s->user->name ?? '',
                'nik' => $s->user->nik ?? '',
                'no_agenda' => $s->user->no_agenda ?? '',
                'skor_pg' => $s->skor_pg,
                'skor_benar_salah' => $s->skor_benar_salah,
                'skor_labeling' => $s->skor_labeling,
                'skor_menjodohkan' => $s->skor_menjodohkan,
                'skor_total' => $s->skor_total,
                'is_lulus' => $s->is_lulus,
                'waktu_selesai' => $s->waktu_selesai?->toIso8601String(),
            ])
            ->all();

        $distribusi = ['0-25' => 0, '26-50' => 0, '51-75' => 0, '76-100' => 0];
        foreach ($skorList as $skor) {
            if ($skor <= 25) {
                $distribusi['0-25']++;
            } elseif ($skor <= 50) {
                $distribusi['26-50']++;
            } elseif ($skor <= 75) {
                $distribusi['51-75']++;
            } else {
                $distribusi['76-100']++;
            }
        }

        return [
            'total_peserta' => $totalPeserta,
            'selesai' => $selesai,
            'sedang_berlangsung' => $sedangBerlangsung,
            'belum_mulai' => $belumMulai,
            'lulus' => $lulus,
            'tidak_lulus' => $tidakLulus,
            'rata_rata_skor' => $rataSkor,
            'tertinggi' => $tertinggi,
            'terendah' => $terendah,
            'distribusi' => $distribusi,
            'detail' => $detail,
        ];
    }
}
