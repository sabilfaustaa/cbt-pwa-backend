<?php

use App\Enums\RoleName;
use App\Enums\StatusSesi;
use App\Enums\TipeSoal;
use App\Models\JadwalUjian;
use App\Models\Jawaban;
use App\Models\OpsiSoal;
use App\Models\Role;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\User;

test('Role cast works', function () {
    $role = new Role(['nama_role' => RoleName::Admin, 'deskripsi' => 'Admin']);
    expect($role->nama_role)->toBe(RoleName::Admin);
});

test('User belongsTo Role', function () {
    $role = Role::create(['nama_role' => RoleName::Peserta]);
    $user = User::create([
        'role_id' => $role->id,
        'name' => 'Test',
        'nik' => '3201010101010001',
        'no_agenda' => 'A001',
    ]);

    expect($user->role)->not->toBeNull();
    expect($user->role->nama_role)->toBe(RoleName::Peserta);
    expect($user->namaRole)->toBe(RoleName::Peserta);
});

test('Soal hasMany OpsiSoal', function () {
    $role = Role::create(['nama_role' => RoleName::Admin]);
    $admin = User::create(['role_id' => $role->id, 'name' => 'Admin', 'email' => 'admin@test.com']);
    $soal = Soal::create(['tipe' => TipeSoal::Pg, 'pertanyaan' => 'Test?', 'created_by' => $admin->id]);
    $opsi1 = OpsiSoal::create(['soal_id' => $soal->id, 'teks' => 'A', 'is_kunci' => true]);
    $opsi2 = OpsiSoal::create(['soal_id' => $soal->id, 'teks' => 'B']);

    expect($soal->opsi)->toHaveCount(2);
    expect($opsi1->soal->id)->toBe($soal->id);
});

test('SesiUjian cast + relations', function () {
    $role = Role::create(['nama_role' => RoleName::Peserta]);
    $user = User::create(['role_id' => $role->id, 'name' => 'Peserta', 'nik' => '3201010101010002', 'no_agenda' => 'A002']);
    $adminRole = Role::create(['nama_role' => RoleName::Admin]);
    $admin = User::create(['role_id' => $adminRole->id, 'name' => 'Admin2', 'email' => 'admin2@test.com']);

    $jadwal = JadwalUjian::create([
        'kode_jadwal' => 'TEST-001',
        'nama_ujian' => 'Ujian Test',
        'waktu_mulai' => now(),
        'waktu_selesai' => now()->addHours(2),
        'durasi_menit' => 60,
        'created_by' => $admin->id,
    ]);

    $sesi = SesiUjian::create([
        'jadwal_ujian_id' => $jadwal->id,
        'user_id' => $user->id,
        'status' => StatusSesi::BelumMulai,
    ]);

    // Cast assertions
    expect($sesi->status)->toBe(StatusSesi::BelumMulai);
    expect($sesi->waktu_batas)->toBeNull(); // belum mulai

    // Relation assertions
    expect($sesi->user->id)->toBe($user->id);
    expect($sesi->jadwalUjian->id)->toBe($jadwal->id);
});

test('Jawaban belongsTo relations', function () {
    $role = Role::create(['nama_role' => RoleName::Peserta]);
    $user = User::create(['role_id' => $role->id, 'name' => 'Peserta', 'nik' => '3201010101010003', 'no_agenda' => 'A003']);
    $adminRole = Role::create(['nama_role' => RoleName::Admin]);
    $admin = User::create(['role_id' => $adminRole->id, 'name' => 'Admin3', 'email' => 'admin3@test.com']);

    $jadwal = JadwalUjian::create([
        'kode_jadwal' => 'TEST-002',
        'nama_ujian' => 'Ujian Test 2',
        'waktu_mulai' => now(),
        'waktu_selesai' => now()->addHours(2),
        'durasi_menit' => 60,
        'created_by' => $admin->id,
    ]);

    $soal = Soal::create(['tipe' => TipeSoal::Pg, 'pertanyaan' => 'Test?', 'created_by' => $admin->id]);
    $opsi = OpsiSoal::create(['soal_id' => $soal->id, 'teks' => 'A', 'is_kunci' => true]);

    $sesi = SesiUjian::create([
        'jadwal_ujian_id' => $jadwal->id,
        'user_id' => $user->id,
        'status' => StatusSesi::SedangBerlangsung,
        'waktu_mulai' => now(),
        'waktu_batas' => now()->addHour(),
    ]);

    $jawaban = Jawaban::create([
        'sesi_ujian_id' => $sesi->id,
        'soal_id' => $soal->id,
        'opsi_id' => $opsi->id,
    ]);

    expect($jawaban->sesiUjian->id)->toBe($sesi->id);
    expect($jawaban->soal->id)->toBe($soal->id);
    expect($jawaban->opsi->id)->toBe($opsi->id);
});
