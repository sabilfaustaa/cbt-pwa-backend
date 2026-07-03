<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string|null $email
 * @property-read string|null $nik
 * @property-read string|null $no_agenda
 * @property-read bool $is_active
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 * @property-read Role|null $role
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->name,
            'email' => $this->email,
            'nik' => $this->nik,
            'no_agenda' => $this->no_agenda,
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role->id,
                'nama_role' => $this->role->nama_role->value,
            ]),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
