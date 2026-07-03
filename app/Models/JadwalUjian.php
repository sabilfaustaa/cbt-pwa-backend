<?php

namespace App\Models;

use App\Enums\StatusJadwal;
use Database\Factories\JadwalUjianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @phpstan-use HasFactory<JadwalUjianFactory>
 *
 * @property StatusJadwal $status
 * @property Carbon|null $waktu_mulai
 * @property Carbon|null $waktu_selesai
 * @property bool $acak_soal
 * @property bool $acak_opsi
 * @property int $durasi_menit
 */
class JadwalUjian extends Model
{
    /** @phpstan-use HasFactory<JadwalUjianFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'jadwal_ujian';

    protected $fillable = [
        'kode_jadwal',
        'nama_ujian',
        'deskripsi',
        'waktu_mulai',
        'waktu_selesai',
        'durasi_menit',
        'acak_soal',
        'acak_opsi',
        'tampilkan_hasil',
        'passing_grade',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusJadwal::class,
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
            'acak_soal' => 'boolean',
            'acak_opsi' => 'boolean',
            'tampilkan_hasil' => 'boolean',
        ];
    }

    // ─── Relasi ─────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<JadwalPeserta, $this> */
    public function jadwalPeserta(): HasMany
    {
        return $this->hasMany(JadwalPeserta::class);
    }

    /** @return HasMany<JadwalSoal, $this> */
    public function jadwalSoal(): HasMany
    {
        return $this->hasMany(JadwalSoal::class);
    }

    public function soal(): BelongsToMany
    {
        return $this->belongsToMany(Soal::class, 'jadwal_soal')
            ->withPivot('nomor_urut')
            ->withTimestamps();
    }

    /** @return HasMany<SesiUjian, $this> */
    public function sesiUjian(): HasMany
    {
        return $this->hasMany(SesiUjian::class);
    }
}
