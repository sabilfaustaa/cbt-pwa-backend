<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $nomor_urut
 * @property-read Soal|null $soal
 * @property-read JadwalUjian|null $jadwalUjian
 */
class JadwalSoal extends Model
{
    protected $table = 'jadwal_soal';

    protected $fillable = [
        'jadwal_ujian_id',
        'soal_id',
        'nomor_urut',
    ];

    // ─── Relasi ─────────────────────────────────────────────────

    public function jadwalUjian(): BelongsTo
    {
        return $this->belongsTo(JadwalUjian::class);
    }

    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class);
    }
}
