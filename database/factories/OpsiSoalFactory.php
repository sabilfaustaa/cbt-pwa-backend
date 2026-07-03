<?php

namespace Database\Factories;

use App\Models\OpsiSoal;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpsiSoal>
 */
class OpsiSoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'soal_id' => Soal::factory(),
            'teks' => fake()->word(),
            'pasangan' => null,
            'nomor_urut' => null,
            'is_kunci' => false,
        ];
    }

    public function kunci(): static
    {
        return $this->state(fn () => ['is_kunci' => true]);
    }

    public function denganNomor(int $nomor): static
    {
        return $this->state(fn () => ['nomor_urut' => $nomor]);
    }

    public function denganPasangan(string $pasangan): static
    {
        return $this->state(fn () => ['pasangan' => $pasangan]);
    }
}
