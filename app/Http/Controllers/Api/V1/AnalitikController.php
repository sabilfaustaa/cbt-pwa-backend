<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Models\JadwalUjian;
use App\Services\AnalitikService;
use Illuminate\Http\JsonResponse;

class AnalitikController
{
    // ─── GET /jadwal-ujian/:id/analitik ─────────────────────────

    public function analitik(int $id, AnalitikService $analitikService): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);

        if ($jadwal->status->value !== 'selesai') {
            return ApiResponse::error('Analitik hanya tersedia setelah ujian selesai.', 422);
        }

        $data = $analitikService->analitik($jadwal);

        return ApiResponse::success($data, 'Analitik butir soal.');
    }
}
