<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $jadwal_ujian_id
 * @property int $user_id
 * @property string $token_akses
 * @property User $user
 * @property JadwalUjian $jadwalUjian
 */
class JadwalPeserta extends Model
{
    protected $table = 'jadwal_peserta';

    protected $fillable = [
        'jadwal_ujian_id',
        'user_id',
        'token_akses',
    ];

    // ─── Relasi ─────────────────────────────────────────────────

    public function jadwalUjian(): BelongsTo
    {
        return $this->belongsTo(JadwalUjian::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
