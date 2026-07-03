<?php

namespace App\Http\Resources;

use App\Enums\StatusSesi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $jadwal_ujian_id
 * @property int $user_id
 * @property Carbon|null $waktu_mulai
 * @property Carbon|null $waktu_batas
 * @property Carbon|null $waktu_selesai
 * @property StatusSesi $status
 * @property float|null $skor_pg
 * @property float|null $skor_benar_salah
 * @property float|null $skor_labeling
 * @property float|null $skor_menjodohkan
 * @property float|null $skor_total
 * @property bool|null $is_lulus
 * @property string|null $ip_mulai
 * @property string|null $user_agent_mulai
 * @property int $jumlah_pelanggaran
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SesiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'jadwal_ujian_id' => $this->jadwal_ujian_id,
            'user_id' => $this->user_id,
            'waktu_mulai' => $this->waktu_mulai?->toIso8601String(),
            'waktu_batas' => $this->waktu_batas?->toIso8601String(),
            'waktu_selesai' => $this->waktu_selesai?->toIso8601String(),
            'status' => $this->status->value,
            'skor_pg' => $this->skor_pg,
            'skor_benar_salah' => $this->skor_benar_salah,
            'skor_labeling' => $this->skor_labeling,
            'skor_menjodohkan' => $this->skor_menjodohkan,
            'skor_total' => $this->skor_total,
            'is_lulus' => $this->is_lulus,
            'ip_mulai' => $this->ip_mulai,
            'user_agent_mulai' => $this->user_agent_mulai,
            'jumlah_pelanggaran' => $this->jumlah_pelanggaran,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
