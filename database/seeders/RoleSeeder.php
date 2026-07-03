<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::create(['nama_role' => RoleName::Admin,    'deskripsi' => 'Administrator sistem CBT — akses penuh ke semua fitur manajemen']);
        Role::create(['nama_role' => RoleName::Pengawas, 'deskripsi' => 'Pengawas ujian — memantau sesi, menambah waktu, dan membatalkan sesi peserta']);
        Role::create(['nama_role' => RoleName::Peserta,  'deskripsi' => 'Peserta ujian — mengerjakan soal via antarmuka CBT']);
    }
}
