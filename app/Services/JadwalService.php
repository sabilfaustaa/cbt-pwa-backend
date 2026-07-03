<?php

namespace App\Services;

use App\Enums\StatusJadwal;
use App\Enums\StatusSesi;
use App\Models\JadwalUjian;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class JadwalService
{
    public function __construct(
        private TokenService $tokenService,
    ) {}

    /**
     * State machine valid + lempar 409 jika transisi ilegal.
     *
     * @return array{allowed: bool, error?: string}
     */
    public function validateStatusTransition(StatusJadwal $current, StatusJadwal $target): array
    {
        $allowed = match ($current) {
            StatusJadwal::Draft => [StatusJadwal::Terbuka, StatusJadwal::Dibatalkan],
            StatusJadwal::Terbuka => [StatusJadwal::Berlangsung, StatusJadwal::Dibatalkan],
            StatusJadwal::Berlangsung => [StatusJadwal::Selesai, StatusJadwal::Dibatalkan],
            StatusJadwal::Selesai, StatusJadwal::Dibatalkan => [],
        };

        if (! in_array($target, $allowed, true)) {
            $allowedStr = implode(', ', array_map(fn (StatusJadwal $s): string => $s->value, $allowed));

            return [
                'allowed' => false,
                'error' => empty($allowed)
                    ? "Status '{$current->value}' sudah final dan tidak bisa diubah."
                    : "Transisi dari '{$current->value}' ke '{$target->value}' tidak diperbolehkan. Status yang diizinkan: {$allowedStr}.",
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Assign peserta ke jadwal secara transaksional.
     * Idempotent: user yang sudah ter-assign skip.
     *
     * @param  int[]  $userIds
     * @return array{assigned: int, failed: int, tokens: array<int, array{user_id: int, token_akses: string}>}
     */
    public function assignPeserta(JadwalUjian $jadwal, array $userIds): array
    {
        $assigned = 0;
        $failed = 0;
        $tokens = [];
        $now = now();

        foreach ($userIds as $userId) {
            // Cek user ada & role peserta
            $user = User::where('id', $userId)
                ->whereHas('role', fn ($q) => $q->where('nama_role', 'peserta'))
                ->first();

            if (! $user) {
                $failed++;

                continue;
            }

            // Idempotent: cek sudah ter-assign
            $already = $jadwal->jadwalPeserta()->where('user_id', $userId)->exists();
            if ($already) {
                $failed++;

                continue;
            }

            // Bikin token
            $token = $this->tokenService->generate();

            // Insert jadwal_peserta
            $jadwal->jadwalPeserta()->create([
                'user_id' => $userId,
                'token_akses' => $token,
            ]);

            // Create sesi_ujian
            $sesi = $jadwal->sesiUjian()->create([
                'user_id' => $userId,
                'status' => StatusSesi::BelumMulai,
            ]);

            $tokens[] = [
                'user_id' => $userId,
                'token_akses' => $token,
            ];

            $assigned++;
        }

        return [
            'assigned' => $assigned,
            'failed' => $failed,
            'tokens' => $tokens,
        ];
    }

    /**
     * Cek apakah jadwal bisa dihapus.
     * Throw 409 jika jadwal punya sesi.
     */
    public function assertDeletable(JadwalUjian $jadwal): void
    {
        if ($jadwal->sesiUjian()->exists()) {
            throw new HttpException(
                409,
                'Jadwal tidak bisa dihapus karena sudah memiliki sesi ujian.'
            );
        }
    }
}
