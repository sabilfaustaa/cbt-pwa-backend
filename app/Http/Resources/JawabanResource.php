<?php

namespace App\Http\Resources;

use App\Models\Jawaban;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sesi_ujian_id
 * @property int $soal_id
 * @property int|null $opsi_id
 * @property bool|null $jawaban_bool
 * @property int|null $nomor_jawaban
 * @property int|null $pasangan_opsi_id
 * @property bool|null $is_benar
 * @property float|null $poin_didapat
 * @property Carbon|null $waktu_jawab
 */
class JawabanResource extends JsonResource
{
    public function __construct(Jawaban $resource)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sesi_ujian_id' => $this->sesi_ujian_id,
            'soal_id' => $this->soal_id,
            'opsi_id' => $this->opsi_id,
            'jawaban_bool' => $this->jawaban_bool,
            'nomor_jawaban' => $this->nomor_jawaban,
            'pasangan_opsi_id' => $this->pasangan_opsi_id,
            'is_benar' => $this->is_benar,
            'poin_didapat' => $this->poin_didapat,
            'waktu_jawab' => $this->waktu_jawab?->toIso8601String(),
        ];
    }
}
