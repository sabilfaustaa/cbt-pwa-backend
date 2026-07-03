<?php

namespace App\Models;

use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $nik
 * @property string|null $no_agenda
 * @property bool $is_active
 * @property-read RoleName|null $namaRole
 * @property-read Role|null $role
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'nik',
        'no_agenda',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ─── Accessors ─────────────────────────────────────────────

    public function namaRole(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->role?->nama_role,
        );
    }

    /**
     * Nama kolom di ERD = `nama`, kolom di DB = `name`.
     * Accessor untuk kompatibilitas.
     */
    public function nama(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->name,
            set: fn (string $value) => ['name' => $value],
        );
    }

    // ─── Relasi ─────────────────────────────────────────────────

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function jadwalPeserta(): HasMany
    {
        return $this->hasMany(JadwalPeserta::class);
    }

    public function sesiUjian(): HasMany
    {
        return $this->hasMany(SesiUjian::class);
    }

    public function jadwalDibuat(): HasMany
    {
        return $this->hasMany(JadwalUjian::class, 'created_by');
    }

    public function soalDibuat(): HasMany
    {
        return $this->hasMany(Soal::class, 'created_by');
    }
}
