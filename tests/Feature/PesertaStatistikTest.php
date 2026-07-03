<?php

use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('GET /peserta/statistik mengembalikan statistik peserta', function () {
    // A001 sudah punya sesi TIK selesai (skor lengkap dari SimulasiSesiSeeder)
    $token = $this->postJson('/api/v1/auth/login', [
        'nik' => '3201010101010001',
        'no_agenda' => 'A001',
    ])->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/peserta/statistik');

    $res->assertStatus(200);
    expect($res->json('data'))->toHaveKeys(['ujian_diikuti', 'ujian_lulus', 'rata_rata_skor', 'last_ujian_at']);
    // A001 menyelesaikan ujian TIK
    expect($res->json('data.ujian_diikuti'))->toBeGreaterThanOrEqual(1);
    expect($res->json('data.ujian_lulus'))->toBeGreaterThanOrEqual(1);
    expect($res->json('data.rata_rata_skor'))->toBeNumeric();
    expect($res->json('data.last_ujian_at'))->not->toBeNull();
});

test('GET /peserta/statistik admin → 403', function () {
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ])->json('data.token');

    $this->withToken($token)->getJson('/api/v1/peserta/statistik')->assertStatus(403);
});

test('GET /peserta/statistik tanpa autentikasi → 401', function () {
    $this->getJson('/api/v1/peserta/statistik')->assertStatus(401);
});

test('GET /auth/me menyertakan no_agenda untuk peserta', function () {
    $token = $this->postJson('/api/v1/auth/login', [
        'nik' => '3201010101010001',
        'no_agenda' => 'A001',
    ])->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/auth/me');

    $res->assertStatus(200);
    expect($res->json('data.user'))->toHaveKey('no_agenda');
    expect($res->json('data.user.no_agenda'))->toBe('A001');
});
