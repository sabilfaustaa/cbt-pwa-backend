<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $waktu_jawab
 */
class Jawaban extends Model
{
    protected $table = 'jawaban';

    protected $fillable = [
        'sesi_ujian_id',
        'soal_id',
        'opsi_id',
        'jawaban_bool',
        'nomor_jawaban',
        'pasangan_opsi_id',
        'idempotency_key',
        // is_benar, poin_didapat TIDAK fillable — diisi service saat scoring
        'waktu_jawab',
    ];

    protected function casts(): array
    {
        return [
            'jawaban_bool' => 'boolean',
            'is_benar' => 'boolean',
            'poin_didapat' => 'double',
            'waktu_jawab' => 'datetime',
        ];
    }

    // ─── Relasi ─────────────────────────────────────────────────

    public function sesiUjian(): BelongsTo
    {
        return $this->belongsTo(SesiUjian::class);
    }

    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class);
    }

    public function opsi(): BelongsTo
    {
        return $this->belongsTo(OpsiSoal::class, 'opsi_id');
    }

    public function pasanganOpsi(): BelongsTo
    {
        return $this->belongsTo(OpsiSoal::class, 'pasangan_opsi_id');
    }
}
