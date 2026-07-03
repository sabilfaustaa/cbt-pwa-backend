<?php

namespace Database\Factories;

use App\Enums\StatusSesi;
use App\Models\JadwalUjian;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SesiUjian>
 */
class SesiUjianFactory extends Factory
{
    public function definition(): array
    {
        return [
            'jadwal_ujian_id' => JadwalUjian::factory(),
            'user_id' => User::factory(),
            'status' => StatusSesi::BelumMulai,
            'waktu_mulai' => null,
            'waktu_batas' => null,
            'waktu_selesai' => null,
            'jumlah_pelanggaran' => 0,
        ];
    }

    public function berlangsung(): static
    {
        return $this->state(fn () => [
            'status' => StatusSesi::SedangBerlangsung,
            'waktu_mulai' => now(),
            'waktu_batas' => now()->addMinutes(60),
        ]);
    }

    public function selesai(): static
    {
        return $this->state(fn () => [
            'status' => StatusSesi::Selesai,
            'waktu_mulai' => now()->subMinutes(90),
            'waktu_batas' => now()->subMinutes(30),
            'waktu_selesai' => now()->subMinutes(30),
        ]);
    }
}
