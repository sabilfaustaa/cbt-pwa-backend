<?php

namespace App\Services;

use App\Enums\StatusSesi;
use App\Models\JadwalPeserta;
use App\Models\JadwalSoal;
use App\Models\Jawaban;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SesiService
{
    public function validateToken(User $user, string $token): JadwalPeserta
    {
        $jadwalPeserta = JadwalPeserta::with('jadwalUjian')
            ->where('token_akses', $token)
            ->first();

        if (! $jadwalPeserta) {
            throw new HttpException(403, 'Token tidak valid.');
        }

        if ($jadwalPeserta->user_id !== $user->id) {
            throw new HttpException(403, 'Token tidak valid.');
        }

        $jadwal = $jadwalPeserta->jadwalUjian;

        // Gate status: peserta hanya boleh memulai jika admin sudah membuka jadwal
        // (status 'terbuka') atau ujian sedang 'berlangsung'. Jadwal 'draft' yang
        // belum dipublikasikan, atau yang sudah 'selesai'/'dibatalkan', tidak boleh dimulai.
        if (in_array($jadwal->status->value, ['selesai', 'dibatalkan'], true)) {
            throw new HttpException(409, 'Jadwal ujian sudah tidak tersedia.');
        }

        if (! in_array($jadwal->status->value, ['terbuka', 'berlangsung'], true)) {
            throw new HttpException(409, 'Jadwal ujian belum dibuka oleh admin.');
        }

        $now = now();
        if ($jadwal->waktu_mulai && $now->lt($jadwal->waktu_mulai)) {
            throw new HttpException(409, 'Jadwal ujian belum dimulai.');
        }
        if ($jadwal->waktu_selesai && $now->gt($jadwal->waktu_selesai)) {
            throw new HttpException(409, 'Jadwal ujian sudah berakhir.');
        }

        return $jadwalPeserta;
    }

    public function mulaiSesi(JadwalPeserta $jadwalPeserta, string $ip, string $userAgent, bool $persetujuan = false): SesiUjian
    {
        $sesi = SesiUjian::where('jadwal_ujian_id', $jadwalPeserta->jadwal_ujian_id)
            ->where('user_id', $jadwalPeserta->user_id)
            ->first();

        if (! $sesi) {
            throw new HttpException(500, 'Sesi tidak ditemukan. Hubungi admin.');
        }

        if ($sesi->status === StatusSesi::SedangBerlangsung) {
            return $sesi;
        }

        if ($sesi->status !== StatusSesi::BelumMulai) {
            throw new HttpException(409, 'Sesi tidak bisa dimulai karena status: '.$sesi->status->value.'.');
        }

        $durasiMenit = $jadwalPeserta->jadwalUjian->durasi_menit;
        $now = now();

        $payload = [
            'waktu_mulai' => $now,
            'waktu_batas' => $now->copy()->addMinutes($durasiMenit),
            'status' => StatusSesi::SedangBerlangsung,
            'ip_mulai' => $ip,
            'user_agent_mulai' => $userAgent,
        ];

        // Audit trail pakta integritas (opsional, backward compatible)
        if ($persetujuan) {
            $payload['persetujuan_at'] = $now;
            $payload['ip_persetujuan'] = $ip;
        }

        $sesi->update($payload);

        return $sesi->fresh();
    }

    public function sisaDetik(SesiUjian $sesi): int
    {
        if (! $sesi->waktu_batas) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($sesi->waktu_batas, false));
    }

    public function soalCount(SesiUjian $sesi): int
    {
        return JadwalSoal::where('jadwal_ujian_id', $sesi->jadwal_ujian_id)->count();
    }

    public function upsertJawaban(
        SesiUjian $sesi,
        Soal $soal,
        ?int $opsiId,
        ?bool $jawabanBool,
        ?int $nomorJawaban,
        ?int $pasanganOpsiId,
        ?string $idempotencyKey = null,
    ): Jawaban {
        $data = [
            'sesi_ujian_id' => $sesi->id,
            'soal_id' => $soal->id,
            'opsi_id' => $opsiId,
            'jawaban_bool' => $jawabanBool,
            'nomor_jawaban' => $nomorJawaban,
            'pasangan_opsi_id' => $pasanganOpsiId,
            'idempotency_key' => $idempotencyKey,
            'waktu_jawab' => now(),
        ];

        // F-03: tipe single-answer (pg & benar_salah) di-upsert per (sesi, soal) —
        // TANPA opsi_id di kunci, supaya mengganti jawaban meng-UPDATE baris yang
        // sama, bukan menambah baris baru yang membuat skor & review bertentangan.
        // Tipe multi-baris (labeling & menjodohkan) memang satu baris per opsi.
        if (in_array($soal->tipe->value, ['pg', 'benar_salah'], true)) {
            $jawaban = Jawaban::where('sesi_ujian_id', $sesi->id)
                ->where('soal_id', $soal->id)
                ->orderByDesc('id')
                ->first();

            if ($jawaban) {
                // Bersihkan baris duplikat sisa kunci upsert lama
                Jawaban::where('sesi_ujian_id', $sesi->id)
                    ->where('soal_id', $soal->id)
                    ->where('id', '!=', $jawaban->id)
                    ->delete();
                $jawaban->update($data);
            } else {
                $jawaban = Jawaban::create($data);
            }
        } else {
            $jawaban = Jawaban::updateOrCreate(
                ['sesi_ujian_id' => $sesi->id, 'soal_id' => $soal->id, 'opsi_id' => $opsiId],
                $data,
            );
        }

        return $jawaban;
    }
}
