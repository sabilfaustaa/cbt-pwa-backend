<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $judul
 * @property string $isi
 * @property string $penulis
 * @property bool $is_penting
 * @property int|null $jadwal_id
 * @property Carbon|null $published_at
 */
class Pengumuman extends Model
{
    protected $table = 'pengumuman';

    protected $fillable = [
        'judul',
        'isi',
        'penulis',
        'is_penting',
        'jadwal_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_penting' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    // ─── Relasi ─────────────────────────────────────────────────

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalUjian::class, 'jadwal_id');
    }
}
