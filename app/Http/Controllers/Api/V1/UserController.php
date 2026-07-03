<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Helpers\ApiResponse;
use App\Http\Requests\User\BulkImportRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List user — filter role, q (cari nama/email/nik), is_active; paginate.
     */
    public function index(): JsonResponse
    {
        $users = User::with('role')
            ->when(request('role'), function (Builder $q, $role) {
                $q->whereRelation('role', 'nama_role', $role);
            })
            ->when(request('q'), function (Builder $q, $search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            })
            ->when(request()->has('is_active'), function (Builder $q) {
                $q->where('is_active', request()->boolean('is_active'));
            })
            ->orderBy('id')
            ->paginate(perPage: min((int) request('per_page', 20), 100));

        $users->getCollection()->transform(fn ($user) => (new UserResource($user))->resolve());

        return ApiResponse::successPaginated($users, 'Daftar user.');
    }

    /**
     * Detail user by ID.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with('role')->findOrFail($id);

        return ApiResponse::success(new UserResource($user));
    }

    /**
     * Create user — kondisional petugas/peserta.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['password']);
        $data['is_active'] = $request->boolean('is_active', true);

        // FE mengirim 'nama'; DB column adalah 'name'
        if (isset($data['nama'])) {
            $data['name'] = $data['nama'];
            unset($data['nama']);
        }

        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $user = User::create($data);

        return ApiResponse::created(new UserResource($user->load('role')), 'User berhasil dibuat.');
    }

    /**
     * Update user partial.
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $data = $request->safe()->except(['password']);

        // FE mengirim 'nama'; DB column adalah 'name'
        if (isset($data['nama'])) {
            $data['name'] = $data['nama'];
            unset($data['nama']);
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return ApiResponse::success(new UserResource($user->fresh('role')), 'User berhasil diperbarui.');
    }

    /**
     * Hapus user — 409 bila sudah di-assign atau punya sesi.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Cek apakah user sudah punya sesi atau di-assign ke jadwal
        if ($user->sesiUjian()->exists() || $user->jadwalPeserta()->exists()) {
            return ApiResponse::error('User tidak bisa dihapus karena sudah terdaftar di jadwal ujian.', 409);
        }

        $user->delete();

        return ApiResponse::success(null, 'User berhasil dihapus.');
    }

    /**
     * Bulk import peserta via CSV (nama,nik,no_agenda).
     */
    public function bulkImport(BulkImportRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return ApiResponse::error('Gagal membaca file.', 400);
        }

        // Lookup peserta role ID secara dinamis agar aman dengan Postgres sequences
        $pesertaRoleId = Role::where('nama_role', RoleName::Peserta)->value('id');

        $imported = 0;
        $errors = [];
        $row = 0;

        // Skip header jika ada (deteksi kata "nama" atau "nik" di baris pertama)
        $first = fgetcsv($handle);
        if ($first !== false && in_array(strtolower($first[0]), ['nama', 'name'], true)) {
            $row = 1; // header ditemukan
        } else {
            // Bukan header — proses baris pertama
            rewind($handle);
        }

        while (($cols = fgetcsv($handle)) !== false) {
            $row++;

            $cols = array_map('trim', $cols);
            $nama = $cols[0];
            $nik = $cols[1] ?? '';
            $noAgenda = $cols[2] ?? '';

            if (empty($nama) || empty($nik) || empty($noAgenda)) {
                $errors[] = ['row' => $row, 'message' => "Baris {$row}: data tidak lengkap (nama/NIK/no_agenda wajib)."];

                continue;
            }

            if (mb_strlen($nik) !== 16 || ! ctype_digit($nik)) {
                $errors[] = ['row' => $row, 'message' => "Baris {$row}: NIK '{$nik}' tidak valid (harus 16 digit)."];

                continue;
            }

            if (User::where('nik', $nik)->exists()) {
                $errors[] = ['row' => $row, 'message' => "Baris {$row}: NIK '{$nik}' sudah terdaftar."];

                continue;
            }

            User::create([
                'role_id' => $pesertaRoleId,
                'name' => $nama,
                'nik' => $nik,
                'no_agenda' => $noAgenda,
                'is_active' => true,
            ]);

            $imported++;
        }

        fclose($handle);

        return ApiResponse::success([
            'imported' => $imported,
            'failed' => count($errors),
            'errors' => $errors,
        ], $imported > 0 ? 'Import selesai.' : 'Tidak ada data yang diimpor.');
    }
}
