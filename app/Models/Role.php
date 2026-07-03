<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property RoleName $nama_role
 * @property string|null $deskripsi
 */
class Role extends Model
{
    protected $fillable = [
        'nama_role',
        'deskripsi',
    ];

    protected function casts(): array
    {
        return [
            'nama_role' => RoleName::class,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
