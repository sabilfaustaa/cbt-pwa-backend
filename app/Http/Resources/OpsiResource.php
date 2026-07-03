<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $soal_id
 * @property-read string $teks
 * @property-read string|null $pasangan
 * @property-read int|null $nomor_urut
 * @property-read bool $is_kunci
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 */
class OpsiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'soal_id' => $this->soal_id,
            'teks' => $this->teks,
            'pasangan' => $this->pasangan,
            'nomor_urut' => $this->nomor_urut,
            'is_kunci' => $this->is_kunci,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
