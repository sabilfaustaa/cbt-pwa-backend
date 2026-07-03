<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Helpers\ApiResponse;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login — otomatis deteksi admin/pengawas (email+password)
     * atau peserta (nik+no_agenda).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if ($request->isAdminLogin()) {
            return $this->loginAdmin($request);
        }

        if ($request->isPesertaLogin()) {
            return $this->loginPeserta($request);
        }

        return ApiResponse::error(
            'Gunakan email+password (admin/pengawas) atau NIK+no_agenda (peserta).',
            422
        );
    }

    /**
     * Logout — revoke token yang sedang dipakai.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->tokens()->delete();

        return ApiResponse::success(null, 'Berhasil logout.');
    }

    /**
     * Info user yang sedang login (beserta role).
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');

        return ApiResponse::success([
            'user' => $this->userResponse($user),
        ]);
    }

    // ─── internal ──────────────────────────────────────────

    private function loginAdmin(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            AuditLog::create([
                'user_id' => null,
                'action' => 'auth.login.failed',
                'entity_type' => 'user',
                'entity_id' => null,
                'metadata' => ['email' => $request->email, 'reason' => 'invalid_credentials'],
            ]);

            return ApiResponse::error('Email atau password salah.', 401);
        }

        if (! in_array($user->namaRole->value, ['admin', 'pengawas'], true)) {
            return ApiResponse::error('Akun ini tidak bisa login via email.', 403);
        }

        return $this->respondWithToken($user, false);
    }

    private function loginPeserta(LoginRequest $request): JsonResponse
    {
        $peserta = User::where('nik', $request->nik)
            ->where('no_agenda', $request->no_agenda)
            ->whereHas('role', fn ($q) => $q->where('nama_role', RoleName::Peserta))
            ->first();

        if (! $peserta) {
            AuditLog::create([
                'user_id' => null,
                'action' => 'auth.login.failed',
                'entity_type' => 'user',
                'entity_id' => null,
                'metadata' => ['no_agenda' => $request->no_agenda, 'reason' => 'invalid_credentials'],
            ]);

            return ApiResponse::error('NIK atau nomor agenda tidak ditemukan.', 401);
        }

        if (! $peserta->is_active) {
            return ApiResponse::error('Akun peserta tidak aktif.', 403);
        }

        return $this->respondWithToken($peserta, true);
    }

    private function respondWithToken(User $user, bool $withSesiAktif): JsonResponse
    {
        $expirationMinutes = (int) config('sanctum.expiration', 60);

        $token = $user->createToken(
            'auth-token',
            ['*'],
            Carbon::now()->addMinutes($expirationMinutes)
        )->plainTextToken;

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'auth.login.success',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => ['role' => $user->namaRole->value],
        ]);

        $data = [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expirationMinutes * 60,   // detik, sesuai tipe FE
            'user' => $this->userResponse($user),
        ];

        if ($withSesiAktif) {
            $sesiAktif = SesiUjian::where('user_id', $user->id)
                ->where('status', 'sedang_berlangsung')
                ->select(['id', 'jadwal_ujian_id', 'status', 'waktu_batas'])
                ->first();

            $data['sesi_aktif'] = $sesiAktif
                ? [
                    'id' => $sesiAktif->id,
                    'jadwal_ujian_id' => $sesiAktif->jadwal_ujian_id,
                    'status' => $sesiAktif->status->value,
                    'sisa_detik' => max(0, (int) now()->diffInSeconds($sesiAktif->waktu_batas, false)),
                ]
                : null;
        }

        return ApiResponse::success($data, 'Login berhasil.');
    }

    /**
     * Shape user sesuai tipe FE: `frontend/src/types/auth.ts → User`
     * role sebagai objek {id, nama_role}, sertakan created_at & updated_at.
     *
     * @return array<string, mixed>
     */
    private function userResponse(User $user): array
    {
        $user->loadMissing('role');

        return [
            'id' => $user->id,
            'nama' => $user->name,
            'email' => $user->email,
            'nik' => $user->nik,
            'no_agenda' => $user->no_agenda,
            'role' => [
                'id' => $user->role?->id,
                'nama_role' => $user->namaRole->value,
            ],
            'is_active' => $user->is_active,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
