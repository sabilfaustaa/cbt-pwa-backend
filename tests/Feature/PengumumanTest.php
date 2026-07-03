<?php

use App\Models\Pengumuman;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->adminToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ])->json('data.token');

    $this->pesertaToken = $this->postJson('/api/v1/auth/login', [
        'nik' => '3201010101010001',
        'no_agenda' => 'A001',
    ])->json('data.token');
});

// ═══ Index ════════════════════════════════════════════════════

test('GET /pengumuman mengembalikan data + total dengan shape backend-natural', function () {
    $res = $this->withToken($this->adminToken)->getJson('/api/v1/pengumuman');

    $res->assertStatus(200);
    expect($res->json('data'))->toHaveKeys(['data', 'total']);
    expect($res->json('data.total'))->toBeGreaterThanOrEqual(4);
    $item = $res->json('data.data.0');
    expect($item)->toHaveKeys(['id', 'judul', 'isi', 'penulis', 'is_penting', 'jadwal_id', 'published_at', 'created_at']);
});

test('GET /pengumuman peserta hanya melihat yang sudah dipublikasikan', function () {
    // Buat draft (published_at null)
    Pengumuman::create([
        'judul' => 'Draft Rahasia',
        'isi' => 'Belum dipublikasikan',
        'penulis' => 'Administrator',
        'is_penting' => false,
        'published_at' => null,
    ]);
    // Buat terjadwal masa depan
    Pengumuman::create([
        'judul' => 'Masa Depan',
        'isi' => 'Tayang nanti',
        'penulis' => 'Administrator',
        'is_penting' => false,
        'published_at' => now()->addDays(3),
    ]);

    $res = $this->withToken($this->pesertaToken)->getJson('/api/v1/pengumuman');
    $res->assertStatus(200);

    $judulList = collect($res->json('data.data'))->pluck('judul');
    expect($judulList)->not->toContain('Draft Rahasia');
    expect($judulList)->not->toContain('Masa Depan');
});

test('GET /pengumuman admin melihat draft', function () {
    Pengumuman::create([
        'judul' => 'Draft Admin',
        'isi' => 'Isi draft',
        'penulis' => 'Administrator',
        'is_penting' => false,
        'published_at' => null,
    ]);

    $res = $this->withToken($this->adminToken)->getJson('/api/v1/pengumuman');
    $res->assertStatus(200);
    expect(collect($res->json('data.data'))->pluck('judul'))->toContain('Draft Admin');
});

// ═══ Store ════════════════════════════════════════════════════

test('POST /pengumuman admin membuat pengumuman', function () {
    $res = $this->withToken($this->adminToken)->postJson('/api/v1/pengumuman', [
        'judul' => 'Pengumuman Baru',
        'isi' => 'Isi pengumuman baru.',
        'is_penting' => true,
    ]);

    $res->assertStatus(201);
    $res->assertJsonPath('data.judul', 'Pengumuman Baru');
    $res->assertJsonPath('data.is_penting', true);
    expect($res->json('data.published_at'))->not->toBeNull();
    expect($res->json('data.penulis'))->not->toBeEmpty();
});

test('POST /pengumuman tanpa judul → 422', function () {
    $this->withToken($this->adminToken)->postJson('/api/v1/pengumuman', [
        'isi' => 'Tanpa judul',
    ])->assertStatus(422);
});

test('POST /pengumuman peserta → 403', function () {
    $this->withToken($this->pesertaToken)->postJson('/api/v1/pengumuman', [
        'judul' => 'X',
        'isi' => 'Y',
    ])->assertStatus(403);
});

// ═══ Update & Delete ══════════════════════════════════════════

test('PUT /pengumuman/:id admin mengupdate', function () {
    $p = Pengumuman::first();

    $res = $this->withToken($this->adminToken)->putJson("/api/v1/pengumuman/{$p->id}", [
        'judul' => 'Judul Diupdate',
    ]);

    $res->assertStatus(200);
    $res->assertJsonPath('data.judul', 'Judul Diupdate');
});

test('DELETE /pengumuman/:id admin menghapus', function () {
    $p = Pengumuman::first();

    $this->withToken($this->adminToken)->deleteJson("/api/v1/pengumuman/{$p->id}")->assertStatus(200);
    expect(Pengumuman::find($p->id))->toBeNull();
});

test('DELETE /pengumuman/:id peserta → 403', function () {
    $p = Pengumuman::first();

    $this->withToken($this->pesertaToken)->deleteJson("/api/v1/pengumuman/{$p->id}")->assertStatus(403);
});
