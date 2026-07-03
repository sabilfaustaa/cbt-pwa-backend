<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $jadwal_ujian_id
 * @property-read int $user_id
 * @property-read string $token_akses
 * @property-read User $user
 */
class JadwalPesertaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'nama' => $this->user->name,
                'nik' => $this->user->nik ?? '',
            ],
            'token_akses' => $this->token_akses,
            'sesi_status' => $this->resource->sesi_status ?? null,
            'skor_total' => $this->resource->skor_total ?? null,
        ];
    }
}
