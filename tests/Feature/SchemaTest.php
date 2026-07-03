<?php

use App\Enums\RoleName;
use App\Models\JadwalUjian;
use App\Models\Role;
use App\Models\SesiUjian;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('tabel kunci exists', function () {
    $tables = [
        'roles', 'users', 'jadwal_ujian', 'soal', 'opsi_soal',
        'jadwal_soal', 'jadwal_peserta', 'sesi_ujian', 'jawaban',
        'audit_log', 'sesi_aktivitas',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Tabel {$table} harus ada");
    }
});

test('roles has required columns', function () {
    expect(Schema::hasColumns('roles', ['id', 'nama_role', 'deskripsi', 'created_at', 'updated_at']))
        ->toBeTrue();
});

test('users has required columns', function () {
    $columns = ['id', 'role_id', 'name', 'email', 'password', 'nik', 'no_agenda', 'is_active', 'deleted_at'];
    expect(Schema::hasColumns('users', $columns))->toBeTrue();
});

test('jadwal_ujian has required columns', function () {
    $columns = ['id', 'kode_jadwal', 'nama_ujian', 'deskripsi', 'waktu_mulai', 'waktu_selesai',
        'durasi_menit', 'acak_soal', 'acak_opsi', 'tampilkan_hasil', 'passing_grade', 'status',
        'created_by', 'deleted_at'];
    expect(Schema::hasColumns('jadwal_ujian', $columns))->toBeTrue();
});

test('soal has required columns', function () {
    $columns = ['id', 'tipe', 'pertanyaan', 'media_url', 'poin', 'jawaban_benar_bool',
        'pembahasan', 'created_by', 'deleted_at'];
    expect(Schema::hasColumns('soal', $columns))->toBeTrue();
});

test('opsi_soal has required columns', function () {
    $columns = ['id', 'soal_id', 'teks', 'pasangan', 'nomor_urut', 'is_kunci'];
    expect(Schema::hasColumns('opsi_soal', $columns))->toBeTrue();
});

test('jadwal_soal has required columns', function () {
    $columns = ['id', 'jadwal_ujian_id', 'soal_id', 'nomor_urut'];
    expect(Schema::hasColumns('jadwal_soal', $columns))->toBeTrue();
});

test('jadwal_peserta has required columns', function () {
    $columns = ['id', 'jadwal_ujian_id', 'user_id', 'token_akses'];
    expect(Schema::hasColumns('jadwal_peserta', $columns))->toBeTrue();
});

test('sesi_ujian has required columns + unique constraint rejects duplicate', function () {
    $columns = ['id', 'jadwal_ujian_id', 'user_id', 'waktu_mulai', 'waktu_batas',
        'waktu_selesai', 'status', 'skor_pg', 'skor_benar_salah', 'skor_labeling',
        'skor_menjodohkan', 'skor_total', 'is_lulus', 'ip_mulai', 'user_agent_mulai',
        'jumlah_pelanggaran'];
    expect(Schema::hasColumns('sesi_ujian', $columns))->toBeTrue();

    // Test unique (jadwal_ujian_id, user_id) menolak duplikat
    $role = Role::create(['nama_role' => RoleName::Peserta]);
    $user = User::create(['role_id' => $role->id, 'name' => 'Peserta', 'nik' => '3201999999990001', 'no_agenda' => 'Z001']);
    $adminRole = Role::create(['nama_role' => RoleName::Admin]);
    $admin = User::create(['role_id' => $adminRole->id, 'name' => 'Admin', 'email' => 'adminschema@test.com']);

    $jadwal = JadwalUjian::create([
        'kode_jadwal' => 'SCH-001',
        'nama_ujian' => 'Schema Test',
        'waktu_mulai' => now(),
        'waktu_selesai' => now()->addHours(2),
        'durasi_menit' => 60,
        'created_by' => $admin->id,
    ]);

    SesiUjian::create([
        'jadwal_ujian_id' => $jadwal->id,
        'user_id' => $user->id,
    ]);

    expect(fn () => SesiUjian::create([
        'jadwal_ujian_id' => $jadwal->id,
        'user_id' => $user->id,
    ]))->toThrow(QueryException::class);
});

test('jawaban has required columns', function () {
    $columns = ['id', 'sesi_ujian_id', 'soal_id', 'opsi_id', 'jawaban_bool',
        'nomor_jawaban', 'pasangan_opsi_id', 'is_benar', 'poin_didapat', 'waktu_jawab'];
    expect(Schema::hasColumns('jawaban', $columns))->toBeTrue();
});

test('audit_log has required columns + jsonb metadata', function () {
    $columns = ['id', 'user_id', 'action', 'entity_type', 'entity_id', 'metadata', 'created_at'];
    expect(Schema::hasColumns('audit_log', $columns))->toBeTrue();
});

test('sesi_aktivitas has required columns', function () {
    $columns = ['id', 'sesi_ujian_id', 'jenis', 'metadata', 'created_at'];
    expect(Schema::hasColumns('sesi_aktivitas', $columns))->toBeTrue();
});
