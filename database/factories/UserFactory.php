<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'role_id' => Role::where('nama_role', RoleName::Peserta)->value('id') ?? 3, // default peserta
            'name' => fake()->name(),
            'email' => null,
            'password' => null,
            'nik' => fake()->unique()->numerify('3201##########'),
            'no_agenda' => fake()->unique()->bothify('?###'),
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::where('nama_role', RoleName::Admin)->value('id') ?? 1,
            'nik' => null,
            'no_agenda' => null,
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
        ]);
    }

    public function pengawas(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::where('nama_role', RoleName::Pengawas)->value('id') ?? 2,
            'nik' => null,
            'no_agenda' => null,
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
        ]);
    }

    public function peserta(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::where('nama_role', RoleName::Peserta)->value('id') ?? 3,
            'email' => null,
            'password' => null,
        ]);
    }
}
