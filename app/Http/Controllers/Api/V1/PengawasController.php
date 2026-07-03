<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusSesi;
use App\Helpers\ApiResponse;
use App\Http\Requests\Pengawas\BatalkanSesiRequest;
use App\Http\Requests\Pengawas\TambahWaktuRequest;
use App\Models\AuditLog;
use App\Models\JadwalSoal;
use App\Models\SesiUjian;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PengawasController
{
    // ─── GET /pengawas/jadwal/:id/monitor ──────────────────────

    public function monitor(int $jadwalId): JsonResponse
    {
        $totalSoal = JadwalSoal::where('jadwal_ujian_id', $jadwalId)->count();

        $sesiList = SesiUjian::with('user:id,name,nik')
            ->withCount([
                'jawaban as jumlah_dijawab' => fn ($q) => $q->select(
                    DB::raw('count(distinct soal_id)')
                ),
            ])
            ->where('jadwal_ujian_id', $jadwalId)
            ->get();

        $items = $sesiList->map(function (SesiUjian $sesi) use ($totalSoal) {
            $sisaDetik = 0;
            if ($sesi->status === StatusSesi::SedangBerlangsung && $sesi->waktu_batas) {
                $sisaDetik = max(0, (int) now()->diffInSeconds($sesi->waktu_batas, false));
            }

            return [
                'sesi_id' => $sesi->id,
                'user' => [
                    'id' => $sesi->user?->id,
                    'nama' => $sesi->user?->name,
                    'nik' => $sesi->user?->nik,
                ],
                'status' => $sesi->status->value,
                'sisa_detik' => $sisaDetik,
                'waktu_mulai' => $sesi->waktu_mulai?->toIso8601String(),
                'waktu_batas' => $sesi->waktu_batas?->toIso8601String(),
                'jumlah_dijawab' => (int) $sesi->jumlah_dijawab,
                'total_soal' => $totalSoal,
                'jumlah_pelanggaran' => $sesi->jumlah_pelanggaran,
            ];
        });

        return ApiResponse::success($items->values(), 'Data monitor jadwal.');
    }

    // ─── POST /pengawas/sesi/:id/tambah-waktu ──────────────────

    public function tambahWaktu(int $id, TambahWaktuRequest $request): JsonResponse
    {
        $sesi = SesiUjian::findOrFail($id);

        if ($sesi->status !== StatusSesi::SedangBerlangsung) {
            throw new HttpException(409, 'Hanya sesi yang sedang berlangsung yang bisa ditambah waktunya.');
        }

        DB::transaction(function () use ($sesi, $request) {
            $menit = (int) $request->input('tambahan_menit');
            $sesi->waktu_batas = $sesi->waktu_batas
                ? $sesi->waktu_batas->addMinutes($menit)
                : now()->addMinutes($menit);
            $sesi->save();

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'sesi.tambah_waktu',
                'entity_type' => 'sesi_ujian',
                'entity_id' => $sesi->id,
                'metadata' => [
                    'tambahan_menit' => $menit,
                    'alasan' => $request->input('alasan'),
                    'waktu_batas_baru' => $sesi->waktu_batas->toIso8601String(),
                ],
            ]);
        });

        $sesi->refresh();

        return ApiResponse::success([
            'sesi' => (new \App\Http\Resources\SesiResource($sesi))->resolve(),
        ], 'Waktu ujian berhasil ditambah.');
    }

    // ─── POST /pengawas/sesi/:id/batalkan ──────────────────────

    public function batalkan(int $id, BatalkanSesiRequest $request): JsonResponse
    {
        $sesi = SesiUjian::findOrFail($id);

        if (in_array($sesi->status->value, ['selesai', 'dibatalkan', 'kadaluarsa'], true)) {
            throw new HttpException(409, 'Sesi sudah berakhir dan tidak bisa dibatalkan.');
        }

        DB::transaction(function () use ($sesi, $request) {
            $sesi->status = StatusSesi::Dibatalkan;
            $sesi->waktu_selesai = now();
            $sesi->save();

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'sesi.batalkan',
                'entity_type' => 'sesi_ujian',
                'entity_id' => $sesi->id,
                'metadata' => ['alasan' => $request->input('alasan')],
            ]);
        });

        $sesi->refresh();

        return ApiResponse::success([
            'sesi' => (new \App\Http\Resources\SesiResource($sesi))->resolve(),
        ], 'Sesi berhasil dibatalkan.');
    }
}
