<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusSesi;
use App\Helpers\ApiResponse;
use App\Models\SesiUjian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PesertaController
{
    // ─── GET /peserta/statistik ─────────────────────────────────

    public function statistik(Request $request): JsonResponse
    {
        $user = $request->user();

        $sesiSelesai = SesiUjian::where('user_id', $user->id)
            ->where('status', StatusSesi::Selesai)
            ->get();

        $ujianDiikuti = $sesiSelesai->count();
        $ujianLulus = $sesiSelesai->where('is_lulus', true)->count();

        $skorList = $sesiSelesai->pluck('skor_total')->filter(fn ($v) => $v !== null);
        $rataRataSkor = $skorList->isNotEmpty() ? round((float) $skorList->avg(), 1) : 0.0;

        $lastUjian = $sesiSelesai
            ->filter(fn (SesiUjian $s) => $s->waktu_selesai !== null)
            ->sortByDesc('waktu_selesai')
            ->first();

        return ApiResponse::success([
            'ujian_diikuti' => $ujianDiikuti,
            'ujian_lulus' => $ujianLulus,
            'rata_rata_skor' => $rataRataSkor,
            'last_ujian_at' => $lastUjian?->waktu_selesai?->toIso8601String(),
        ], 'Statistik peserta.');
    }
}
