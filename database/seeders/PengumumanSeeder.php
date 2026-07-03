<?php

namespace Database\Seeders;

use App\Models\JadwalUjian;
use App\Models\Pengumuman;
use Illuminate\Database\Seeder;

/**
 * 5 skenario pengumuman untuk test coverage lengkap.
 *
 * ┌────────────────────────────────────────────────────────────────────────┐
 * │ #  │ Status      │ Penting │ Jadwal Link         │ Kasus Test         │
 * ├────────────────────────────────────────────────────────────────────────┤
 * │ 1  │ published   │ Ya      │ -                   │ Global penting     │
 * │ 2  │ published   │ Ya      │ -                   │ Global tata tertib │
 * │ 3  │ published   │ Tidak   │ UAS-2026-MTK │ Linked ke aktif    │
 * │ 4  │ draft       │ Tidak   │ -                   │ Belum published    │
 * │ 5  │ published   │ Tidak   │ UAS-2026-TIK     │ Hasil ujian lalu   │
 * └────────────────────────────────────────────────────────────────────────┘
 *
 * Peserta hanya melihat #1, #2, #3, #5 (published).
 * Admin melihat semua termasuk #4 (draft).
 */
class PengumumanSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $jadwalBerlangsung = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->first();
        $jadwalSelesai     = JadwalUjian::where('kode_jadwal', 'UAS-2026-TIK')->first();

        $items = [
            // ── 1. Published, penting, global ─────────────────────────────────
            [
                'judul'        => 'Selamat Datang di CBT Online UAS Genap 2025/2026',
                'isi'          => 'Pelaksanaan Ujian Akhir Semester Genap dilaksanakan secara daring. '
                    . 'Pastikan koneksi internet stabil dan perangkat dalam kondisi baik sebelum memulai ujian. '
                    . 'Penggunaan token akses bersifat rahasia dan tidak boleh dibagikan kepada siapapun.',
                'penulis'      => 'Administrator',
                'is_penting'   => true,
                'jadwal_id'    => null,
                'published_at' => $now->copy()->subDays(5),
            ],

            // ── 2. Published, penting, global — tata tertib ───────────────────
            [
                'judul'        => 'Tata Tertib Pelaksanaan Ujian',
                'isi'          => 'Peserta wajib menyetujui pakta integritas sebelum memulai ujian. '
                    . 'Selama ujian berlangsung: (1) Dilarang berpindah tab atau jendela browser. '
                    . '(2) Wajib menggunakan mode layar penuh (fullscreen). '
                    . '(3) Dilarang menggunakan alat bantu apapun. '
                    . 'Pelanggaran akan dicatat dan dilaporkan ke pengawas.',
                'penulis'      => 'Administrator',
                'is_penting'   => true,
                'jadwal_id'    => null,
                'published_at' => $now->copy()->subDays(3),
            ],

            // ── 3. Published, tidak penting, linked ke jadwal berlangsung ─────
            [
                'judul'        => 'Pengingat: Ujian Matematika Sedang Berlangsung',
                'isi'          => 'Bagi peserta yang terjadwal mengikuti ujian Matematika Dasar, '
                    . 'harap segera masuk ke ruang ujian menggunakan token akses yang telah dibagikan. '
                    . 'Sisa waktu ujian sekitar 55 menit. Hubungi pengawas jika mengalami kendala.',
                'penulis'      => 'Pengawas Ujian',
                'is_penting'   => false,
                'jadwal_id'    => $jadwalBerlangsung?->id,
                'published_at' => $now->copy()->subHours(2),
            ],

            // ── 4. Draft: belum dipublikasikan ───────────────────────────────
            [
                'judul'        => '[DRAFT] Pengumuman Remedial UAS Semester Genap',
                'isi'          => 'Peserta yang tidak mencapai KKM pada UAS Genap diwajibkan mengikuti '
                    . 'program remedial. Jadwal remedial akan diumumkan dalam waktu dekat. '
                    . 'Harap pantau pengumuman ini secara berkala.',
                'penulis'      => 'Administrator',
                'is_penting'   => false,
                'jadwal_id'    => null,
                'published_at' => null, // null = masih draft
            ],

            // ── 5. Published, linked ke jadwal yang sudah selesai ─────────────
            [
                'judul'        => 'Hasil Ujian TIK Telah Tersedia',
                'isi'          => 'Hasil Ujian Akhir Semester mata pelajaran Teknologi Informasi & Komunikasi '
                    . 'telah tersedia. Peserta dapat melihat skor, detail jawaban, dan pembahasan '
                    . 'melalui halaman riwayat ujian masing-masing. '
                    . 'Peserta yang belum mencapai KKM (70) diharapkan menghubungi guru mata pelajaran.',
                'penulis'      => 'Pengawas Ujian',
                'is_penting'   => false,
                'jadwal_id'    => $jadwalSelesai?->id,
                'published_at' => $now->copy()->subDay(),
            ],
        ];

        foreach ($items as $item) {
            Pengumuman::create($item);
        }
    }
}
