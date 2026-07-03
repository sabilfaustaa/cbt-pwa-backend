<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_role' => fake()->randomElement(RoleName::cases()),
            'deskripsi' => fake()->sentence(),
        ];
    }
}
