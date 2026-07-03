<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Requests\Pengumuman\StorePengumumanRequest;
use App\Http\Requests\Pengumuman\UpdatePengumumanRequest;
use App\Models\Pengumuman;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengumumanController
{
    // ─── GET /pengumuman ────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $role = $request->user()->namaRole->value ?? '';
        $isAdmin = $role === 'admin';

        $query = Pengumuman::query()->orderByDesc('published_at')->orderByDesc('created_at');

        // Non-admin (peserta/pengawas) hanya melihat yang sudah dipublikasikan
        if (! $isAdmin) {
            $query->whereNotNull('published_at')->where('published_at', '<=', now());
        }

        $list = $query->get()->map(fn (Pengumuman $p) => $this->toArray($p))->values()->all();

        return ApiResponse::success([
            'data' => $list,
            'total' => count($list),
        ], 'Daftar pengumuman.');
    }

    // ─── POST /pengumuman ───────────────────────────────────────

    public function store(StorePengumumanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['penulis'] = $request->user()->name;
        $data['is_penting'] = (bool) ($data['is_penting'] ?? false);
        // Jika tanggal publikasi tidak diberikan, publikasikan sekarang
        $data['published_at'] = $request->has('published_at')
            ? $request->input('published_at')
            : now();

        $pengumuman = Pengumuman::create($data);

        return ApiResponse::success($this->toArray($pengumuman), 'Pengumuman berhasil dibuat.', 201);
    }

    // ─── PUT /pengumuman/:id ────────────────────────────────────

    public function update(UpdatePengumumanRequest $request, int $id): JsonResponse
    {
        $pengumuman = Pengumuman::findOrFail($id);
        $pengumuman->update($request->validated());

        return ApiResponse::success($this->toArray($pengumuman->fresh()), 'Pengumuman berhasil diupdate.');
    }

    // ─── DELETE /pengumuman/:id ─────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $pengumuman = Pengumuman::findOrFail($id);
        $pengumuman->delete();

        return ApiResponse::success(['id' => $id], 'Pengumuman berhasil dihapus.');
    }

    // ─── internal ──────────────────────────────────────────

    /**
     * Shape backend-natural (FE mengadaptasi is_penting→penting, published_at→tanggal).
     *
     * @return array<string, mixed>
     */
    private function toArray(Pengumuman $p): array
    {
        return [
            'id' => $p->id,
            'judul' => $p->judul,
            'isi' => $p->isi,
            'penulis' => $p->penulis,
            'is_penting' => $p->is_penting,
            'jadwal_id' => $p->jadwal_id,
            'published_at' => $p->published_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }
}
