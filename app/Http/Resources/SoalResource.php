<?php

namespace App\Http\Resources;

use App\Models\OpsiSoal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read string $tipe
 * @property-read string $pertanyaan
 * @property-read string|null $media_url
 * @property-read int $poin
 * @property-read string|null $pembahasan
 * @property-read bool|null $jawaban_benar_bool
 * @property-read int|null $created_by
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 * @property-read Collection<int, OpsiSoal> $opsi
 */
class SoalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipe' => $this->tipe,
            'pertanyaan' => $this->pertanyaan,
            'media_url' => $this->media_url,
            'poin' => $this->poin,
            'jawaban_benar_bool' => $this->jawaban_benar_bool,
            'pembahasan' => $this->pembahasan,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'opsi' => OpsiResource::collection($this->whenLoaded('opsi')),
        ];
    }
}
