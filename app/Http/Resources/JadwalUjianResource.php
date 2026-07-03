<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read string $kode_jadwal
 * @property-read string $nama_ujian
 * @property-read string|null $deskripsi
 * @property-read Carbon|null $waktu_mulai
 * @property-read Carbon|null $waktu_selesai
 * @property-read int $durasi_menit
 * @property-read bool $acak_soal
 * @property-read bool $acak_opsi
 * @property-read bool $tampilkan_hasil
 * @property-read int $passing_grade
 * @property-read string $status
 * @property-read int $created_by
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 * @property-read int|null $peserta_count
 * @property-read int|null $soal_count
 */
class JadwalUjianResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_jadwal' => $this->kode_jadwal,
            'nama_ujian' => $this->nama_ujian,
            'deskripsi' => $this->deskripsi,
            'waktu_mulai' => $this->waktu_mulai?->toIso8601String(),
            'waktu_selesai' => $this->waktu_selesai?->toIso8601String(),
            'durasi_menit' => $this->durasi_menit,
            'acak_soal' => $this->acak_soal,
            'acak_opsi' => $this->acak_opsi,
            'tampilkan_hasil' => $this->tampilkan_hasil,
            'passing_grade' => $this->passing_grade,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'peserta_count' => $this->whenHas('peserta_count'),
            'soal_count' => $this->whenHas('soal_count'),
        ];
    }
}
