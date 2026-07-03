<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusJadwal;
use App\Helpers\ApiResponse;
use App\Http\Requests\Jadwal\AssignPesertaRequest;
use App\Http\Requests\Jadwal\AttachSoalRequest;
use App\Http\Requests\Jadwal\ReorderSoalRequest;
use App\Http\Requests\Jadwal\StoreJadwalRequest;
use App\Http\Requests\Jadwal\UpdateJadwalRequest;
use App\Http\Requests\Jadwal\UpdateStatusRequest;
use App\Http\Resources\JadwalUjianResource;
use App\Models\JadwalUjian;
use App\Models\SesiUjian;
use App\Services\JadwalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JadwalUjianController
{
    public function __construct(
        private JadwalService $jadwalService,
    ) {}

    // ─── List ─────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $jadwal = JadwalUjian::withCount(['jadwalPeserta as peserta_count', 'jadwalSoal as soal_count'])
            ->when($request->query('status'), function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('nama_ujian', 'like', "%{$search}%")
                        ->orWhere('kode_jadwal', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        $jadwal->getCollection()->transform(fn ($item) => (new JadwalUjianResource($item))->resolve());

        return ApiResponse::successPaginated($jadwal, 'Daftar jadwal ujian.');
    }

    // ─── Detail ───────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $jadwal = JadwalUjian::withCount(['jadwalPeserta as peserta_count', 'jadwalSoal as soal_count'])
            ->findOrFail($id);

        return ApiResponse::success(new JadwalUjianResource($jadwal));
    }

    // ─── Create ───────────────────────────────────────────────

    public function store(StoreJadwalRequest $request): JsonResponse
    {
        $jadwal = JadwalUjian::create($request->jadwalData());

        return ApiResponse::success(
            new JadwalUjianResource($jadwal),
            'Jadwal ujian berhasil dibuat.',
            201
        );
    }

    // ─── Update ───────────────────────────────────────────────

    public function update(UpdateJadwalRequest $request, int $id): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);
        $jadwal->update($request->jadwalData());

        return ApiResponse::success(
            new JadwalUjianResource($jadwal->fresh()),
            'Jadwal berhasil diupdate.'
        );
    }

    // ─── Delete ───────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);
        $this->jadwalService->assertDeletable($jadwal);
        $jadwal->delete();

        return ApiResponse::success(
            ['id' => $id],
            'Jadwal ujian berhasil dihapus.'
        );
    }

    // ─── Status ───────────────────────────────────────────────

    public function updateStatus(UpdateStatusRequest $request, int $id): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);
        $target = StatusJadwal::from($request->input('status'));
        $current = $jadwal->status;

        $result = $this->jadwalService->validateStatusTransition($current, $target);

        if (! $result['allowed']) {
            return ApiResponse::error($result['error'], 409);
        }

        $jadwal->update(['status' => $target]);

        return ApiResponse::success(
            new JadwalUjianResource($jadwal->fresh()),
            "Status jadwal diubah dari '{$current->value}' ke '{$target->value}'."
        );
    }

    // ─── Soal List ────────────────────────────────────────────

    public function soal(int $id): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);

        $result = $jadwal->jadwalSoal()
            ->orderBy('nomor_urut')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'jadwal_ujian_id' => $item->jadwal_ujian_id,
                'soal_id' => $item->soal_id,
                'nomor_urut' => $item->nomor_urut,
            ])
            ->values();

        return ApiResponse::success($result, 'Daftar soal jadwal.');
    }

    // ─── Attach Soal ──────────────────────────────────────────

    public function attachSoal(AttachSoalRequest $request, int $id): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);

        $soalIds = $request->soalIds();
        $existing = $jadwal->jadwalSoal()->whereIn('soal_id', $soalIds)->pluck('soal_id')->toArray();
        $newIds = array_diff($soalIds, $existing);

        if (! empty($newIds)) {
            $maxUrut = $jadwal->jadwalSoal()->max('nomor_urut') ?? 0;
            $inserts = [];
            foreach ($newIds as $soalId) {
                $inserts[] = [
                    'jadwal_ujian_id' => $id,
                    'soal_id' => $soalId,
                    'nomor_urut' => ++$maxUrut,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('jadwal_soal')->insert($inserts);
        }

        return ApiResponse::success(
            ['attached' => count($newIds)],
            'Soal berhasil dilampirkan ke jadwal.',
            201
        );
    }

    // ─── Detach Soal ──────────────────────────────────────────

    public function detachSoal(int $id, int $soalId): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);

        // Guard: komposisi soal tidak boleh diubah saat/usai ujian
        if (in_array($jadwal->status->value, ['berlangsung', 'selesai'], true)) {
            return ApiResponse::error('Soal tidak dapat dilepas saat ujian sedang/sudah berlangsung.', 422);
        }

        $jadwalSoal = $jadwal->jadwalSoal()->where('soal_id', $soalId)->first();

        if (! $jadwalSoal) {
            return ApiResponse::error('Soal tidak terpasang pada jadwal ini.', 404);
        }

        DB::transaction(function () use ($jadwal, $jadwalSoal) {
            $jadwalSoal->delete();

            // Re-sequence nomor_urut agar tetap kontigu (1..N)
            $sisa = $jadwal->jadwalSoal()->orderBy('nomor_urut')->get();
            $urut = 1;
            foreach ($sisa as $js) {
                $js->update(['nomor_urut' => $urut++]);
            }
        });

        return ApiResponse::success(null, 'Soal dilepas dari jadwal.');
    }

    // ─── Reorder Soal ────────────────────────────────────────

    public function reorderSoal(ReorderSoalRequest $request, int $id): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);

        DB::transaction(function () use ($jadwal, $request) {
            foreach ($request->items() as $item) {
                $jadwal->jadwalSoal()->where('soal_id', $item['soal_id'])->update([
                    'nomor_urut' => $item['nomor_urut'],
                ]);
            }
        });

        return ApiResponse::success(
            ['reordered' => count($request->items())],
            'Urutan soal berhasil diupdate.'
        );
    }

    // ─── Peserta List ─────────────────────────────────────────

    public function peserta(int $id): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);

        $jadwalPesertaList = $jadwal->jadwalPeserta()->with('user')->get();
        $result = [];

        foreach ($jadwalPesertaList as $jp) {
            $sesi = SesiUjian::where('jadwal_ujian_id', $id)
                ->where('user_id', $jp->user_id)
                ->first();

            $result[] = [
                'user' => [
                    'id' => $jp->user->id,
                    'nama' => $jp->user->name,
                    'nik' => $jp->user->nik ?? '',
                ],
                'token_akses' => $jp->token_akses,
                'sesi_status' => $sesi?->status?->value,
                'skor_total' => $sesi?->skor_total,
            ];
        }

        return ApiResponse::success($result, 'Daftar peserta jadwal.');
    }

    // ─── Assign Peserta ───────────────────────────────────────

    public function assignPeserta(AssignPesertaRequest $request, int $id): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);

        $result = DB::transaction(fn () => $this->jadwalService->assignPeserta($jadwal, $request->userIds()));

        return ApiResponse::success(
            $result,
            "{$result['assigned']} peserta berhasil di-assign.",
            201
        );
    }

    // ─── Unassign Peserta ─────────────────────────────────────

    public function unassignPeserta(int $id, int $userId): JsonResponse
    {
        $jadwal = JadwalUjian::findOrFail($id);

        $jadwalPeserta = $jadwal->jadwalPeserta()->where('user_id', $userId)->first();

        if (! $jadwalPeserta) {
            return ApiResponse::error('Peserta tidak terdaftar pada jadwal ini.', 404);
        }

        // Guard: peserta yang sudah/sedang ujian tidak boleh dilepas
        $sesi = SesiUjian::where('jadwal_ujian_id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($sesi && in_array($sesi->status->value, ['sedang_berlangsung', 'selesai', 'kadaluarsa'], true)) {
            return ApiResponse::error('Peserta sudah memulai ujian dan tidak dapat dilepas.', 422);
        }

        DB::transaction(function () use ($jadwalPeserta, $sesi) {
            // Hapus sesi 'belum_mulai' yang dibuat saat assign (jika ada)
            $sesi?->delete();
            $jadwalPeserta->delete();
        });

        return ApiResponse::success(null, 'Peserta dilepas dari jadwal.');
    }
}
