<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesiAktivitas extends Model
{
    protected $table = 'sesi_aktivitas';

    public $timestamps = false; // hanya created_at

    protected $fillable = [
        'sesi_ujian_id',
        'jenis',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ─── Relasi ─────────────────────────────────────────────────

    public function sesiUjian(): BelongsTo
    {
        return $this->belongsTo(SesiUjian::class);
    }
}
