<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

// ─── Login ─────────────────────────────────────────────────

test('POST /api/v1/auth/login admin sukses', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ]);

    $response->assertStatus(200);
    expect($response->json('success'))->toBeTrue();
    expect($response->json('data.user.email'))->toBe('admin@cbt.test');
    expect($response->json('data.user.role.nama_role'))->toBe('admin');
    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.token_type'))->toBe('Bearer');
    expect($response->json('data.expires_in'))->toBeInt()->toBeGreaterThan(0);
    expect($response->json('meta.message'))->toBe('Login berhasil.');
});

test('POST /api/v1/auth/login admin gagal — password salah', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'salah',
    ]);

    $response->assertStatus(401);
    expect($response->json('success'))->toBeFalse();
    expect($response->json('data'))->toBeNull();
    expect($response->json('meta.message'))->toBe('Email atau password salah.');
});

test('POST /api/v1/auth/login admin gagal — email tidak ditemukan', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nope@cbt.test',
        'password' => 'password',
    ]);

    $response->assertStatus(401);
});

test('POST /api/v1/auth/login pengawas sukses', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'pengawas1@cbt.test',
        'password' => 'password',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.user.role.nama_role'))->toBe('pengawas');
    expect($response->json('data.user.email'))->toBe('pengawas1@cbt.test');
});

test('POST /api/v1/auth/login peserta sukses', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'nik' => '3201010101010001',
        'no_agenda' => 'A001',
    ]);

    $response->assertStatus(200);
    expect($response->json('success'))->toBeTrue();
    expect($response->json('data.user.nik'))->toBe('3201010101010001');
    expect($response->json('data.user.no_agenda'))->toBe('A001');
    expect($response->json('data.user.role.nama_role'))->toBe('peserta');
    expect($response->json('data.token'))->not->toBeEmpty();
    // Peserta memiliki sesi_aktif (null jika belum mulai)
    expect($response->json('data'))->toHaveKey('sesi_aktif');
});

test('POST /api/v1/auth/login alias peserta sukses', function () {
    $response = $this->postJson('/api/v1/auth/login/peserta', [
        'nik' => '3201010101010001',
        'no_agenda' => 'A001',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.user.role.nama_role'))->toBe('peserta');
});

test('POST /api/v1/auth/login alias petugas sukses', function () {
    $response = $this->postJson('/api/v1/auth/login/petugas', [
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.user.role.nama_role'))->toBe('admin');
});

test('POST /api/v1/auth/login peserta gagal — NIK tidak ditemukan', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'nik' => '9999999999999999',
        'no_agenda' => 'A001',
    ]);

    $response->assertStatus(401);
    expect($response->json('meta.message'))->toBe('NIK atau nomor agenda tidak ditemukan.');
});

test('POST /api/v1/auth/login peserta gagal — no_agenda salah', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'nik' => '3201010101010001',
        'no_agenda' => 'Z999',
    ]);

    $response->assertStatus(401);
});

test('POST /api/v1/auth/login gagal — payload kosong', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertStatus(422);
});

test('POST /api/v1/auth/login gagal — peserta coba login via email', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'peserta@tidak-ada.test',
        'password' => 'password',
    ]);

    $response->assertStatus(401);
});

// ─── Logout ────────────────────────────────────────────────

test('POST /api/v1/auth/logout sukses dengan token valid', function () {
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ]);
    $token = $login->json('data.token');

    $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

    $response->assertStatus(200);
    expect($response->json('success'))->toBeTrue();
    expect($response->json('meta.message'))->toBe('Berhasil logout.');

    $this->flushSession();

    $user = User::where('email', 'admin@cbt.test')->first();
    expect($user->tokens()->count())->toBe(0);
});

test('POST /api/v1/auth/logout tanpa token → 401', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(401);
});

// ─── Me ────────────────────────────────────────────────────

test('GET /api/v1/auth/me dengan token valid', function () {
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ]);
    $token = $login->json('data.token');

    $response = $this->withToken($token)->getJson('/api/v1/auth/me');

    $response->assertStatus(200);
    expect($response->json('success'))->toBeTrue();
    expect($response->json('data.user.email'))->toBe('admin@cbt.test');
    expect($response->json('data.user.role.nama_role'))->toBe('admin');
    expect($response->json('data.user.nama'))->toBe('Administrator');
});

test('GET /api/v1/auth/me tanpa token → 401', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401);
});

test('GET /api/v1/auth/me peserta', function () {
    $login = $this->postJson('/api/v1/auth/login', [
        'nik' => '3201010301010001', // A003 NIK dari UserSeeder
        'no_agenda' => 'A003',
    ]);
    $token = $login->json('data.token');

    $response = $this->withToken($token)->getJson('/api/v1/auth/me');

    $response->assertStatus(200);
    expect($response->json('data.user.role.nama_role'))->toBe('peserta');
    expect($response->json('data.user.nik'))->toBe('3201010301010001');
    expect($response->json('data.user'))->not->toHaveKey('password');
});
