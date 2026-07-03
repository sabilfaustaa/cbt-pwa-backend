<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  CBT PWA — Scenario Seeder (Reusable)                                       ║
 * ║  Studi Kasus: UAS Genap 2025/2026 · SMK Negeri 1 Cibinong · X-TKJ-1        ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Jalankan:  php artisan migrate:fresh --seed                                 ║
 * ║  Semua waktu RELATIF terhadap now() — reusable di hari manapun.              ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  AKUN LOGIN                                                                  ║
 * ║  Admin      : admin@cbt.test          / password                            ║
 * ║  Pengawas 1 : pengawas1@cbt.test      / password                            ║
 * ║  Pengawas 2 : pengawas2@cbt.test      / password                            ║
 * ║  Peserta    : NIK + no_agenda (A001–A030)                                    ║
 * ║    Contoh   : NIK=3201010101010001 / no_agenda=A001                         ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  SKENARIO JADWAL (5 jadwal)                                                  ║
 * ║  ─────────────────────────────────────────────────────────────────────────  ║
 * ║  UAS-DRAFT-001  — draft        (+7 hari, soal ada, peserta belum)            ║
 * ║  UAS-2026-BIND  — terbuka      (hari ini +4 jam, 30 peserta belum mulai)     ║
 * ║  UAS-2026-MTK   — berlangsung  (mulai -35 mnt, selesai +55 mnt)             ║
 * ║    ├─ A001–A005 : sedang_berlangsung – normal (8/14 soal dijawab)            ║
 * ║    ├─ A006–A007 : sedang_berlangsung – hampir habis (waktu_batas +3 mnt)     ║
 * ║    ├─ A008–A009 : sedang_berlangsung – OVERTIME (waktu_batas -5 mnt)         ║
 * ║    ├─ A010      : sedang_berlangsung – 3 pelanggaran tab_blur                ║
 * ║    ├─ A011      : sedang_berlangsung – 5 pelanggaran campuran                ║
 * ║    ├─ A012      : sedang_berlangsung – baru mulai (0 soal)                   ║
 * ║    ├─ A013–A018 : selesai – skor lulus & tidak lulus bervariasi              ║
 * ║    ├─ A019–A023 : belum_mulai                                                 ║
 * ║    ├─ A024–A025 : dibatalkan oleh pengawas                                    ║
 * ║    ├─ A026–A027 : kadaluarsa (auto-submit)                                    ║
 * ║    └─ A028–A030 : sedang_berlangsung – progress awal (3/14 soal)             ║
 * ║  UAS-2026-TIK   — selesai      (2 minggu lalu, semua jawaban + skor)         ║
 * ║    ├─ A001      : skor 100% (sempurna)                                        ║
 * ║    ├─ A002–A015 : skor 70–97% (lulus)                                         ║
 * ║    ├─ A016–A026 : skor 0–68% (tidak lulus)                                    ║
 * ║    ├─ A027      : selesai 43%, 3 pelanggaran tab_blur (gagal)                 ║
 * ║    ├─ A028–A029 : selesai skor rendah < KKM                                   ║
 * ║    └─ A030      : selesai 75%, 2 pelanggaran (lulus)                          ║
 * ║  UAS-BATAL-001  — dibatalkan   (kemarin, 10 peserta, semua dibatalkan)        ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── Hapus semua data lama (FK disabled untuk truncate aman) ───────────
        // RESTART IDENTITY memastikan sequence direset ke 1 setiap run,
        // sehingga ID selalu deterministik — penting untuk test yang hard-code ID.
        Schema::disableForeignKeyConstraints();
        $driver = DB::getDriverName();
        foreach ([
            'audit_log', 'sesi_aktivitas', 'jawaban',
            'sesi_ujian', 'jadwal_peserta', 'jadwal_soal',
            'opsi_soal', 'pengumuman', 'personal_access_tokens',
            'soal', 'jadwal_ujian', 'users', 'roles',
        ] as $table) {
            if ($driver === 'pgsql') {
                // CASCADE diperlukan karena PostgreSQL tetap memeriksa FK
                // meskipun tabel referensi sudah dikosongkan lebih dulu.
                DB::statement("TRUNCATE \"{$table}\" RESTART IDENTITY CASCADE");
            } else {
                DB::table($table)->truncate();
            }
        }
        Schema::enableForeignKeyConstraints();

        // ── Seed dalam urutan FK dependency ───────────────────────────────────
        $this->call(RoleSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(SoalSeeder::class);
        $this->call(JadwalUjianSeeder::class);
        $this->call(SimulasiSesiSeeder::class);
        $this->call(PengumumanSeeder::class);

        // Sequences sudah direset ke 1 oleh TRUNCATE RESTART IDENTITY di atas.
        // Sync ulang ke max(id) agar auto-increment berikutnya tidak bertabrakan
        // jika ada insert tambahan (misal dari test factory).
        if (DB::getDriverName() === 'pgsql') {
            foreach ([
                'roles', 'users', 'soal', 'opsi_soal',
                'jadwal_ujian', 'jadwal_soal', 'jadwal_peserta',
                'sesi_ujian', 'jawaban', 'pengumuman',
            ] as $table) {
                DB::statement(
                    "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), coalesce(max(id), 1)) FROM \"{$table}\""
                );
            }
        }
    }
}
