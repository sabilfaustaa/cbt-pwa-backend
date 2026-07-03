<?php

namespace App\Models;

use App\Enums\StatusSesi;
use Database\Factories\SesiUjianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property StatusSesi $status
 * @property Carbon|null $waktu_mulai
 * @property Carbon|null $waktu_batas
 * @property Carbon|null $waktu_selesai
 * @property float|null $skor_total
 * @property float|null $skor_pg
 * @property float|null $skor_benar_salah
 * @property float|null $skor_labeling
 * @property float|null $skor_menjodohkan
 * @property bool|null $is_lulus
 * @property int $jumlah_pelanggaran
 * @property int $user_id
 * @property int $jadwal_ujian_id
 * @property-read User|null $user
 * @property-read JadwalUjian|null $jadwalUjian
 * @property-read int $jumlah_dijawab
 */
class SesiUjian extends Model
{
    /** @phpstan-use HasFactory<SesiUjianFactory> */
    use HasFactory;

    protected $table = 'sesi_ujian';

    protected $fillable = [
        'jadwal_ujian_id',
        'user_id',
        'waktu_mulai',
        'waktu_batas',
        'waktu_selesai',
        'status',
        // skor_* TIDAK fillable — diisi service via assignment eksplisit
        'ip_mulai',
        'user_agent_mulai',
        'jumlah_pelanggaran',
        'persetujuan_at',
        'ip_persetujuan',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusSesi::class,
            'waktu_mulai' => 'datetime',
            'waktu_batas' => 'datetime',
            'waktu_selesai' => 'datetime',
            'persetujuan_at' => 'datetime',
            'skor_pg' => 'double',
            'skor_benar_salah' => 'double',
            'skor_labeling' => 'double',
            'skor_menjodohkan' => 'double',
            'skor_total' => 'double',
            'is_lulus' => 'boolean',
        ];
    }

    // ─── Relasi ─────────────────────────────────────────────────

    /** @return BelongsTo<JadwalUjian, $this> */
    public function jadwalUjian(): BelongsTo
    {
        return $this->belongsTo(JadwalUjian::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(Jawaban::class);
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(SesiAktivitas::class);
    }
}
