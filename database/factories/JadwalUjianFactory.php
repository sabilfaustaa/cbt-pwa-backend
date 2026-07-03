<?php

namespace Database\Factories;

use App\Enums\StatusJadwal;
use App\Models\JadwalUjian;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JadwalUjian>
 */
class JadwalUjianFactory extends Factory
{
    public function definition(): array
    {
        $mulai = fake()->dateTimeBetween('-1 week', '+1 week');

        return [
            'kode_jadwal' => 'UJI-'.fake()->unique()->year().'-'.fake()->unique()->numerify('####'),
            'nama_ujian' => fake()->sentence(3),
            'deskripsi' => fake()->optional()->paragraph(),
            'waktu_mulai' => $mulai,
            'waktu_selesai' => (clone $mulai)->modify('+7 days'),
            'durasi_menit' => fake()->randomElement([30, 45, 60, 90, 120]),
            'acak_soal' => fake()->boolean(),
            'acak_opsi' => fake()->boolean(),
            'tampilkan_hasil' => true,
            'passing_grade' => 75,
            'status' => StatusJadwal::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function terbuka(): static
    {
        return $this->state(fn () => [
            'status' => StatusJadwal::Terbuka,
        ]);
    }
}
