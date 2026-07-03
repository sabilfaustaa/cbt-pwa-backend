<?php

use App\Models\JadwalUjian;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->adminToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ])->json('data.token');

    $this->jadwalSelesai = JadwalUjian::where('kode_jadwal', 'UAS-2026-TIK')->first();   // selesai
    $this->jadwalBerlangsung = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->first(); // berlangsung
});

test('GET analitik jadwal selesai mengembalikan agregat + butir', function () {
    $res = $this->withToken($this->adminToken)
        ->getJson("/api/v1/jadwal-ujian/{$this->jadwalSelesai->id}/analitik");

    $res->assertStatus(200);
    expect($res->json('success'))->toBeTrue();

    // Agregat
    expect($res->json('data.agregat'))->toHaveKeys([
        'total_peserta', 'selesai', 'rata_rata', 'median',
        'skor_min', 'skor_max', 'lulus', 'distribusi_skor',
    ]);
    expect($res->json('data.agregat.total_peserta'))->toBe(30);
    expect($res->json('data.agregat.selesai'))->toBe(30);

    // distribusi_skor: 7 bin
    $dist = $res->json('data.agregat.distribusi_skor');
    expect($dist)->toBeArray()->toHaveCount(7);
    expect($dist[0])->toHaveKeys(['rentang', 'jumlah']);

    // Butir
    expect($res->json('data.butir'))->toBeArray()->not->toBeEmpty();
    $butir = $res->json('data.butir.0');
    expect($butir)->toHaveKeys(['soal_id', 'nomor_urut', 'tipe', 'stem_ringkas', 'p_value', 'daya_beda']);
    expect($butir['p_value'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
});

test('GET analitik butir PG menyertakan distraktor', function () {
    $res = $this->withToken($this->adminToken)
        ->getJson("/api/v1/jadwal-ujian/{$this->jadwalSelesai->id}/analitik");

    $res->assertStatus(200);

    $pgButir = collect($res->json('data.butir'))->firstWhere('tipe', 'pg');
    expect($pgButir)->not->toBeNull();
    expect($pgButir['distraktor'])->toBeArray()->not->toBeEmpty();
    expect($pgButir['distraktor'][0])->toHaveKeys(['opsi_label', 'jumlah_pilih', 'pct']);
    expect($pgButir['distraktor'][0]['opsi_label'])->toBe('A');
});

test('GET analitik jadwal belum selesai → 422', function () {
    $this->withToken($this->adminToken)
        ->getJson("/api/v1/jadwal-ujian/{$this->jadwalBerlangsung->id}/analitik")
        ->assertStatus(422);
});

test('GET analitik peserta → 403', function () {
    $pesertaToken = $this->postJson('/api/v1/auth/login', [
        'nik' => '3201010101010001',
        'no_agenda' => 'A001',
    ])->json('data.token');

    $this->withToken($pesertaToken)
        ->getJson("/api/v1/jadwal-ujian/{$this->jadwalSelesai->id}/analitik")
        ->assertStatus(403);
});

test('GET analitik tanpa autentikasi → 401', function () {
    $this->getJson("/api/v1/jadwal-ujian/{$this->jadwalSelesai->id}/analitik")
        ->assertStatus(401);
});
