<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsiSoal extends Model
{
    protected $table = 'opsi_soal';

    protected $fillable = [
        'soal_id',
        'teks',
        'pasangan',
        'nomor_urut',
        'is_kunci',
    ];

    protected $hidden = [
        // is_kunci, nomor_urut, pasangan disembunyikan via resource — tidak via $hidden global
    ];

    protected function casts(): array
    {
        return [
            'is_kunci' => 'boolean',
        ];
    }

    // ─── Relasi ─────────────────────────────────────────────────

    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class);
    }
}
