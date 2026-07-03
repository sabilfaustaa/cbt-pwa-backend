<?php

use Illuminate\Support\Facades\Config;

test('GET /api/v1/health returns ApiResponse format', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'data' => ['status' => 'ok'],
    ]);
    $response->assertJsonStructure(['success', 'data', 'meta']);
});

test('ModelNotFoundException returns 404 with ApiResponse format', function () {
    $response = $this->getJson('/api/v1/debug/not-found');

    $response->assertStatus(404);
    $response->assertJson([
        'success' => false,
        'data' => null,
    ]);
    $response->assertJsonPath('meta.message', 'Data tidak ditemukan.');
});

test('RuntimeException returns 500 with ApiResponse format (debug ON)', function () {
    Config::set('app.debug', true);

    $response = $this->getJson('/api/v1/debug/server-error');

    $response->assertStatus(500);
    $response->assertJson([
        'success' => false,
        'data' => null,
    ]);
    // Debug ON: message spesifik
    $response->assertJsonPath('meta.message', 'Simulasi error 500');
});

test('RuntimeException returns 500 with generic message (debug OFF)', function () {
    Config::set('app.debug', false);

    $response = $this->getJson('/api/v1/debug/server-error');

    $response->assertStatus(500);
    $response->assertJson([
        'success' => false,
        'data' => null,
    ]);
    // Debug OFF: pesan generik, tidak bocorkan internal
    $response->assertJsonPath('meta.message', 'Terjadi kesalahan pada server.');
});

test('HttpException 403 returns ApiResponse format', function () {
    $response = $this->getJson('/api/v1/debug/forbidden');

    $response->assertStatus(403);
    $response->assertJson([
        'success' => false,
        'data' => null,
    ]);
    $response->assertJsonPath('meta.message', 'Akses ditolak.');
});

test('ValidationException returns 422 with errors detail', function () {
    $response = $this->getJson('/api/v1/debug/validation');

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'data' => null,
    ]);
    $response->assertJsonPath('meta.message', 'Validasi gagal.');
    $response->assertJsonPath('meta.errors', ['email' => ['Email wajib diisi.']]);
});

test('JadwalTidakAktifException returns custom code + message', function () {
    $response = $this->getJson('/api/v1/debug/jadwal-tidak-aktif');

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'data' => null,
    ]);
    $response->assertJsonPath('meta.message', 'Jadwal ujian tidak aktif.');
});

test('Tidak ada route di api/v1 returns 404 ApiResponse', function () {
    $response = $this->getJson('/api/v1/tidak-ada');

    $response->assertStatus(404);
    $response->assertJson([
        'success' => false,
        'data' => null,
    ]);
    $response->assertJsonPath('meta.message', 'Endpoint tidak ditemukan.');
});
