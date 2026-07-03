<?php

namespace Database\Factories;

use App\Enums\TipeSoal;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Soal>
 */
class SoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tipe' => TipeSoal::Pg,
            'pertanyaan' => fake()->sentence().'?',
            'media_url' => null,
            'poin' => 1,
            'jawaban_benar_bool' => null,
            'pembahasan' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function pg(): static
    {
        return $this->state(fn () => [
            'tipe' => TipeSoal::Pg,
            'jawaban_benar_bool' => null,
        ]);
    }

    public function benarSalah(): static
    {
        return $this->state(fn () => [
            'tipe' => TipeSoal::BenarSalah,
            'jawaban_benar_bool' => fake()->boolean(),
        ]);
    }

    public function labeling(): static
    {
        return $this->state(fn () => [
            'tipe' => TipeSoal::Labeling,
            'media_url' => '/storage/soal/dummy/diagram-'.fake()->numberBetween(1, 3).'.png',
            'jawaban_benar_bool' => null,
        ]);
    }

    public function menjodohkan(): static
    {
        return $this->state(fn () => [
            'tipe' => TipeSoal::Menjodohkan,
            'jawaban_benar_bool' => null,
        ]);
    }
}
