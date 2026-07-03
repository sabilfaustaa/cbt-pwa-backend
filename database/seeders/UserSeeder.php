<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Studi kasus: SMK Negeri 1 Cibinong — Kelas X-TKJ-1
 * UAS Genap Tahun Pelajaran 2025/2026
 *
 * Kredensial:
 *   Admin     : admin@cbt.test        / password
 *   Pengawas 1: pengawas1@cbt.test    / password
 *   Pengawas 2: pengawas2@cbt.test    / password
 *   Peserta   : NIK + no_agenda (lihat tabel di bawah)
 *
 * | No Agenda | NIK              | Nama                   |
 * |-----------|------------------|------------------------|
 * | A001      | 3201010101010001 | Ahmad Fauzi Ramadhan   |
 * | A002      | 3201020201010001 | Aisyah Nur Ramadhani   |
 * | ...       | ...              | ...                    |
 * | A030      | 3201023001010001 | Zahra Nur Aisyah       |
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $idAdmin    = Role::where('nama_role', RoleName::Admin)->value('id');
        $idPengawas = Role::where('nama_role', RoleName::Pengawas)->value('id');
        $idPeserta  = Role::where('nama_role', RoleName::Peserta)->value('id');

        // ── Admin ──────────────────────────────────────────────────────────
        User::create([
            'role_id'   => $idAdmin,
            'name'      => 'Administrator',
            'email'     => 'admin@cbt.test',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);

        // ── Pengawas ───────────────────────────────────────────────────────
        $daftarPengawas = [
            ['name' => 'Bapak Rudi Hartono, S.Kom', 'email' => 'pengawas1@cbt.test'],
            ['name' => 'Ibu Sari Dewi Kusuma, S.Pd', 'email' => 'pengawas2@cbt.test'],
        ];

        foreach ($daftarPengawas as $p) {
            User::create([
                'role_id'   => $idPengawas,
                'name'      => $p['name'],
                'email'     => $p['email'],
                'password'  => Hash::make('password'),
                'is_active' => true,
            ]);
        }

        // ── Peserta — Kelas X-TKJ-1 (30 siswa) ───────────────────────────
        // NIK fiksi: 3201 (Bogor) + 02/01 (P/L) + tanggal lahir + urutan
        // no_agenda: A001–A030
        $daftarPeserta = [
            // ── Baris 1–5: berprestasi tinggi (skor 88–97%)
            ['name' => 'Ahmad Fauzi Ramadhan',   'nik' => '3201010101010001', 'no_agenda' => 'A001'],
            ['name' => 'Aisyah Nur Ramadhani',   'nik' => '3201020201010001', 'no_agenda' => 'A002'],
            ['name' => 'Andika Surya Pratama',   'nik' => '3201010301010001', 'no_agenda' => 'A003'],
            ['name' => 'Annisa Fadhilah Putri',  'nik' => '3201020401010001', 'no_agenda' => 'A004'],
            ['name' => 'Bagas Eko Saputro',      'nik' => '3201010501010001', 'no_agenda' => 'A005'],
            // ── Baris 6–10: di atas rata-rata (skor 77–85%)
            ['name' => 'Bunga Citra Lestari',    'nik' => '3201020601010001', 'no_agenda' => 'A006'],
            ['name' => 'Dani Firmansyah',        'nik' => '3201010701010001', 'no_agenda' => 'A007'],
            ['name' => 'Dewi Anggraeni Safitri', 'nik' => '3201020801010001', 'no_agenda' => 'A008'],
            ['name' => 'Eko Prasetyo Wibowo',   'nik' => '3201010901010001', 'no_agenda' => 'A009'],
            ['name' => 'Fadilla Putri Utami',    'nik' => '3201021001010001', 'no_agenda' => 'A010'],
            // ── Baris 11–15: rata-rata (skor 70–76%)
            ['name' => 'Galih Satria Nugroho',  'nik' => '3201011101010001', 'no_agenda' => 'A011'],
            ['name' => 'Hana Rahayu Safitri',   'nik' => '3201021201010001', 'no_agenda' => 'A012'],
            ['name' => 'Ilham Maulana Akbar',   'nik' => '3201011301010001', 'no_agenda' => 'A013'],
            ['name' => 'Indah Permata Sari',    'nik' => '3201021401010001', 'no_agenda' => 'A014'],
            ['name' => 'Joko Santoso',          'nik' => '3201011501010001', 'no_agenda' => 'A015'],
            // ── Baris 16–20: di bawah KKM (skor 60–68%)
            ['name' => 'Kartika Dwi Lestari',  'nik' => '3201021601010001', 'no_agenda' => 'A016'],
            ['name' => 'Lutfi Hakim Rahman',   'nik' => '3201011701010001', 'no_agenda' => 'A017'],
            ['name' => 'Maya Sari Dewanti',    'nik' => '3201021801010001', 'no_agenda' => 'A018'],
            ['name' => 'Nanda Prasetya Putra', 'nik' => '3201011901010001', 'no_agenda' => 'A019'],
            ['name' => 'Nur Aini Rahmawati',   'nik' => '3201022001010001', 'no_agenda' => 'A020'],
            // ── Baris 21–25: jauh di bawah KKM (skor 54–58%)
            ['name' => 'Oki Wahyudi Setiawan', 'nik' => '3201012101010001', 'no_agenda' => 'A021'],
            ['name' => 'Putri Amalia Rahma',   'nik' => '3201022201010001', 'no_agenda' => 'A022'],
            ['name' => 'Raka Bintang Pratama', 'nik' => '3201012301010001', 'no_agenda' => 'A023'],
            ['name' => 'Rini Oktaviani',       'nik' => '3201022401010001', 'no_agenda' => 'A024'],
            ['name' => 'Satria Wicaksono',     'nik' => '3201012501010001', 'no_agenda' => 'A025'],
            // ── Baris 26–30: perlu perhatian khusus (skor 28–48%)
            ['name' => 'Siti Nurhaliza',       'nik' => '3201022601010001', 'no_agenda' => 'A026'],
            ['name' => 'Taufik Hidayat',       'nik' => '3201012701010001', 'no_agenda' => 'A027'],
            ['name' => 'Tri Wulandari',        'nik' => '3201022801010001', 'no_agenda' => 'A028'],
            ['name' => 'Wahyu Rizky Pratama',  'nik' => '3201012901010001', 'no_agenda' => 'A029'],
            ['name' => 'Zahra Nur Aisyah',     'nik' => '3201023001010001', 'no_agenda' => 'A030'],
        ];

        foreach ($daftarPeserta as $p) {
            User::create([
                'role_id'   => $idPeserta,
                'name'      => $p['name'],
                'nik'       => $p['nik'],
                'no_agenda' => $p['no_agenda'],
                'password'  => null,
                'is_active' => true,
            ]);
        }
    }
}
