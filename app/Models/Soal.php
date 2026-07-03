<?php

namespace App\Models;

use App\Enums\TipeSoal;
use Database\Factories\SoalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property TipeSoal $tipe
 */
class Soal extends Model
{
    /** @phpstan-use HasFactory<SoalFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'soal';

    protected $fillable = [
        'tipe',
        'pertanyaan',
        'media_url',
        'poin',
        'jawaban_benar_bool',
        'pembahasan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tipe' => TipeSoal::class,
            'jawaban_benar_bool' => 'boolean',
        ];
    }

    // ─── Relasi ─────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<OpsiSoal, $this> */
    public function opsi(): HasMany
    {
        return $this->hasMany(OpsiSoal::class);
    }

    public function jadwalSoal(): HasMany
    {
        return $this->hasMany(JadwalSoal::class);
    }

    public function jadwal(): BelongsToMany
    {
        return $this->belongsToMany(JadwalUjian::class, 'jadwal_soal')
            ->withPivot('nomor_urut')
            ->withTimestamps();
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(Jawaban::class);
    }
}
