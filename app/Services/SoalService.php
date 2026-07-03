<?php

namespace App\Services;

use App\Models\Soal;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SoalService
{
    /**
     * Pastikan soal tidak dipakai di jadwal yang sedang berlangsung atau sudah selesai.
     * Lempar 409 jika tidak bisa diedit.
     */
    public function assertEditable(Soal $soal): void
    {
        $locked = $soal->jadwalSoal()
            ->whereHas('jadwalUjian', function ($q) {
                $q->whereIn('status', ['berlangsung', 'selesai']);
            })
            ->exists();

        if ($locked) {
            throw new HttpException(
                409,
                'Soal sudah dipakai di jadwal aktif dan tidak bisa diubah'
            );
        }
    }
}
