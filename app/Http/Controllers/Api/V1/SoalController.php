<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Requests\Soal\StoreSoalRequest;
use App\Http\Requests\Soal\UpdateSoalRequest;
use App\Http\Requests\Soal\UploadMediaRequest;
use App\Http\Resources\SoalResource;
use App\Models\Soal;
use App\Services\SoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SoalController
{
    public function __construct(
        private SoalService $soalService,
    ) {}

    // ─── List ─────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $soal = Soal::with('opsi')
            ->when($request->query('tipe'), function ($q, $tipe) {
                $q->where('tipe', $tipe);
            })
            ->when($request->query('q'), function ($q, $search) {
                $q->where('pertanyaan', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        $soal->getCollection()->transform(fn ($item) => (new SoalResource($item))->resolve());

        return ApiResponse::successPaginated($soal, 'Daftar soal.');
    }

    // ─── Detail ───────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $soal = Soal::with('opsi')->findOrFail($id);

        return ApiResponse::success(new SoalResource($soal));
    }

    // ─── Create ───────────────────────────────────────────────

    public function store(StoreSoalRequest $request): JsonResponse
    {
        $soal = DB::transaction(function () use ($request) {
            $soal = Soal::create($request->soalData());

            if (! empty($request->opsiData())) {
                $soal->opsi()->createMany($request->opsiData());
            }

            return $soal->fresh('opsi');
        });

        return ApiResponse::success(
            new SoalResource($soal),
            'Soal berhasil dibuat.',
            201
        );
    }

    // ─── Update ───────────────────────────────────────────────

    public function update(UpdateSoalRequest $request, int $id): JsonResponse
    {
        $soal = Soal::with('opsi')->findOrFail($id);

        $this->soalService->assertEditable($soal);

        DB::transaction(function () use ($soal, $request) {
            if ($request->hasAny(['pertanyaan', 'tipe', 'media_url', 'poin', 'pembahasan', 'jawaban_benar_bool'])) {
                $soal->update($request->soalData());
            }

            if ($request->hasOpsi()) {
                // Hapus opsi lama, buat baru — lebih sederhana untuk edit
                $soal->opsi()->delete();
                if (! empty($request->opsiData())) {
                    $soal->opsi()->createMany($request->opsiData());
                }
            }
        });

        return ApiResponse::success(
            new SoalResource($soal->fresh('opsi')),
            'Soal berhasil diupdate.'
        );
    }

    // ─── Delete ───────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $soal = Soal::with('opsi')->findOrFail($id);

        $this->soalService->assertEditable($soal);

        $soal->delete();

        return ApiResponse::success(
            ['id' => $id],
            'Soal berhasil dihapus.'
        );
    }

    // ─── Upload Media ─────────────────────────────────────────

    public function uploadMedia(UploadMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');

        // MIME sniff: validasi isi file, bukan ekstensi
        $mime = $file->getMimeType();
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($mime, $allowedMimes, true)) {
            return ApiResponse::error(
                'Format file tidak didukung. Gunakan JPEG, PNG, atau WebP.',
                422,
                ['file' => ['Format file terdeteksi: '.($mime ?: 'unknown')]],
            );
        }

        $path = $file->store('soal/'.now()->format('Y').'/'.now()->format('m'), 'public');
        $url = Storage::disk('public')->url($path);

        return ApiResponse::success(
            ['url' => $url],
            'Media berhasil diunggah.',
            201
        );
    }
}
