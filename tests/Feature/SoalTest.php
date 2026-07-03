<?php

use App\Enums\StatusJadwal;
use App\Models\JadwalUjian;
use App\Models\Soal;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ]);
    $this->token = $login->json('data.token');
});

function authHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

// ═══ List ════════════════════════════════════════════════════

test('GET /api/v1/soal returns paginated soal list with opsi', function () {
    $res = $this->withToken($this->token)->getJson('/api/v1/soal');

    $res->assertStatus(200);
    $res->assertJson(['success' => true]);
    $res->assertJsonStructure(['data', 'meta' => ['pagination']]);
    $items = $res->json('data');
    expect(count($items))->toBeGreaterThan(0);
    // Pastikan opsi termuat
    expect($items[0])->toHaveKey('opsi');
});

test('GET /api/v1/soal filter by tipe', function () {
    $res = $this->withToken($this->token)->getJson('/api/v1/soal?tipe=pg');

    $res->assertStatus(200);
    $items = $res->json('data');
    foreach ($items as $s) {
        expect($s['tipe'])->toBe('pg');
    }
});

test('GET /api/v1/soal filter by q', function () {
    // "komputer" muncul di banyak soal TIK dalam seeder
    $res = $this->withToken($this->token)->getJson('/api/v1/soal?q=komputer');

    $res->assertStatus(200);
    expect(count($res->json('data')))->toBeGreaterThanOrEqual(1);
});

// ═══ Detail ═══════════════════════════════════════════════════

test('GET /api/v1/soal/:id returns soal with opsi', function () {
    $res = $this->withToken($this->token)->getJson('/api/v1/soal/1');

    $res->assertStatus(200);
    $res->assertJsonPath('data.tipe', fn ($v) => strlen($v) > 0);
    expect($res->json('data'))->toHaveKey('opsi');
});

test('GET /api/v1/soal/:id not found → 404', function () {
    $this->withToken($this->token)->getJson('/api/v1/soal/99999')->assertStatus(404);
});

// ═══ Create PG ════════════════════════════════════════════════

test('POST /api/v1/soal creates soal PG', function () {
    $res = $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'pg',
        'pertanyaan' => 'Apa ibu kota Indonesia?',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'Jakarta', 'is_kunci' => true],
            ['teks' => 'Surabaya', 'is_kunci' => false],
            ['teks' => 'Bandung', 'is_kunci' => false],
            ['teks' => 'Medan', 'is_kunci' => false],
        ],
    ]);

    $res->assertStatus(201);
    $res->assertJsonPath('data.tipe', 'pg');
    $res->assertJsonPath('data.jawaban_benar_bool', null);
    expect(count($res->json('data.opsi')))->toBe(4);
});

test('POST /api/v1/soal PG < 2 opsi → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'pg',
        'pertanyaan' => 'Apa?',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'A', 'is_kunci' => true],
        ],
    ])->assertStatus(422);
});

test('POST /api/v1/soal PG 0 kunci → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'pg',
        'pertanyaan' => 'Apa?',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'A', 'is_kunci' => false],
            ['teks' => 'B', 'is_kunci' => false],
        ],
    ])->assertStatus(422);
});

test('POST /api/v1/soal PG 2 kunci → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'pg',
        'pertanyaan' => 'Apa?',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'A', 'is_kunci' => true],
            ['teks' => 'B', 'is_kunci' => true],
        ],
    ])->assertStatus(422);
});

test('POST /api/v1/soal PG with jawaban_benar_bool → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'pg',
        'pertanyaan' => 'Apa?',
        'poin' => 1,
        'jawaban_benar_bool' => true,
        'opsi' => [
            ['teks' => 'A', 'is_kunci' => true],
            ['teks' => 'B', 'is_kunci' => false],
        ],
    ])->assertStatus(422);
});

// ═══ Create Benar-Salah ═══════════════════════════════════════

test('POST /api/v1/soal creates Benar-Salah', function () {
    $res = $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'benar_salah',
        'pertanyaan' => 'Bumi bulat.',
        'poin' => 1,
        'jawaban_benar_bool' => true,
    ]);

    $res->assertStatus(201);
    $res->assertJsonPath('data.jawaban_benar_bool', true);
    expect($res->json('data.opsi'))->toBe([]);
});

test('POST /api/v1/soal Benar-Salah missing jawaban_benar_bool → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'benar_salah',
        'pertanyaan' => 'Bumi bulat.',
        'poin' => 1,
    ])->assertStatus(422);
});

test('POST /api/v1/soal Benar-Salah with opsi → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'benar_salah',
        'pertanyaan' => 'Bumi bulat.',
        'poin' => 1,
        'jawaban_benar_bool' => true,
        'opsi' => [
            ['teks' => 'Benar'],
        ],
    ])->assertStatus(422);
});

// ═══ Create Labeling ══════════════════════════════════════════

test('POST /api/v1/soal creates Labeling', function () {
    $res = $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'labeling',
        'pertanyaan' => 'Tunjuk organ pada gambar.',
        'media_url' => 'https://example.com/anatomy.png',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'Jantung', 'nomor_urut' => 1],
            ['teks' => 'Paru-paru', 'nomor_urut' => 2],
            ['teks' => 'Hati', 'nomor_urut' => 3],
        ],
    ]);

    $res->assertStatus(201);
    $res->assertJsonPath('data.media_url', 'https://example.com/anatomy.png');
    expect(count($res->json('data.opsi')))->toBe(3);
});

test('POST /api/v1/soal Labeling no media_url → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'labeling',
        'pertanyaan' => 'Tunjuk organ.',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'A', 'nomor_urut' => 1],
            ['teks' => 'B', 'nomor_urut' => 2],
        ],
    ])->assertStatus(422);
});

test('POST /api/v1/soal Labeling < 2 opsi → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'labeling',
        'pertanyaan' => 'Tunjuk organ.',
        'media_url' => 'https://example.com/a.png',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'A', 'nomor_urut' => 1],
        ],
    ])->assertStatus(422);
});

test('POST /api/v1/soal Labeling missing nomor_urut → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'labeling',
        'pertanyaan' => 'Tunjuk organ.',
        'media_url' => 'https://example.com/a.png',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'A'],
            ['teks' => 'B'],
        ],
    ])->assertStatus(422);
});

test('POST /api/v1/soal Labeling duplicate nomor_urut → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'labeling',
        'pertanyaan' => 'Tunjuk organ.',
        'media_url' => 'https://example.com/a.png',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'A', 'nomor_urut' => 1],
            ['teks' => 'B', 'nomor_urut' => 1],
        ],
    ])->assertStatus(422);
});

// ═══ Create Menjodohkan ═══════════════════════════════════════

test('POST /api/v1/soal creates Menjodohkan', function () {
    $res = $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'menjodohkan',
        'pertanyaan' => 'Jodohkan negara dengan ibu kota.',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'Indonesia', 'pasangan' => 'Jakarta'],
            ['teks' => 'Jepang', 'pasangan' => 'Tokyo'],
            ['teks' => 'Malaysia', 'pasangan' => 'Kuala Lumpur'],
        ],
    ]);

    $res->assertStatus(201);
    expect(count($res->json('data.opsi')))->toBe(3);
    expect($res->json('data.opsi.0.pasangan'))->toBe('Jakarta');
});

test('POST /api/v1/soal Menjodohkan < 2 opsi → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'menjodohkan',
        'pertanyaan' => 'Jodohkan.',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'A', 'pasangan' => 'B'],
        ],
    ])->assertStatus(422);
});

test('POST /api/v1/soal Menjodohkan missing pasangan → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'menjodohkan',
        'pertanyaan' => 'Jodohkan.',
        'poin' => 1,
        'opsi' => [
            ['teks' => 'A'],
            ['teks' => 'B'],
        ],
    ])->assertStatus(422);
});

// ═══ Update ═══════════════════════════════════════════════════

test('PATCH /api/v1/soal/:id updates soal pertanyaan', function () {
    // Soal ID 40 = BIND PG pertama; jadwal BIND berstatus terbuka → tidak terkunci
    $res = $this->withToken($this->token)->patchJson('/api/v1/soal/40', [
        'pertanyaan' => 'Pertanyaan diupdate.',
    ]);

    $res->assertStatus(200);
    $res->assertJsonPath('data.pertanyaan', 'Pertanyaan diupdate.');
});

test('PATCH /api/v1/soal/:id replaces opsi', function () {
    // Soal ID 40 = BIND PG pertama; jadwal BIND berstatus terbuka → tidak terkunci
    $res = $this->withToken($this->token)->patchJson('/api/v1/soal/40', [
        'opsi' => [
            ['teks' => 'Baru A', 'is_kunci' => true],
            ['teks' => 'Baru B', 'is_kunci' => false],
        ],
    ]);

    $res->assertStatus(200);
    expect(count($res->json('data.opsi')))->toBe(2);
});

// ═══ Delete ═══════════════════════════════════════════════════

test('DELETE /api/v1/soal/:id deletes soal not in jadwal', function () {
    // Buat soal baru yang belum di-attach ke jadwal
    $create = $this->withToken($this->token)->postJson('/api/v1/soal', [
        'tipe' => 'benar_salah',
        'pertanyaan' => 'Soal untuk dihapus.',
        'poin' => 1,
        'jawaban_benar_bool' => false,
    ]);
    $id = $create->json('data.id');

    $res = $this->withToken($this->token)->deleteJson("/api/v1/soal/{$id}");

    $res->assertStatus(200);
    $this->withToken($this->token)->getJson("/api/v1/soal/{$id}")->assertStatus(404);
});

test('DELETE /api/v1/soal/:id in active jadwal → 409', function () {
    // Soal id 1 is in jadwal_soal of jadwal id 1 (status `terbuka` — still editable per spec).
    // But per spec, only berlangsung & selesai are locked.
    // Buat jadwal baru status `berlangsung`, attach soal ke jadwal itu
    $soal = Soal::factory()->create(['tipe' => 'pg']);

    $jadwal = JadwalUjian::factory()->create([
        'status' => StatusJadwal::Berlangsung,
    ]);
    $jadwal->jadwalSoal()->create(['soal_id' => $soal->id, 'nomor_urut' => 1]);

    $this->withToken($this->token)
        ->deleteJson("/api/v1/soal/{$soal->id}")
        ->assertStatus(409);
});

// ═══ Upload Media ═════════════════════════════════════════════

test('POST /api/v1/soal/upload-media uploads image', function () {
    $file = UploadedFile::fake()->image('anatomy.png');
    $res = $this->withToken($this->token)->postJson('/api/v1/soal/upload-media', [
        'file' => $file,
    ]);

    $res->assertStatus(201);
    $res->assertJson(['success' => true]);
    expect($res->json('data.url'))->toContain('/storage/soal/');
});

test('POST /api/v1/soal/upload-media non-image → 422', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
    $this->withToken($this->token)
        ->postJson('/api/v1/soal/upload-media', ['file' => $file])
        ->assertStatus(422);
});

test('POST /api/v1/soal/upload-media > 5MB → 422', function () {
    $file = UploadedFile::fake()->image('big.jpg')->size(6000);
    $this->withToken($this->token)
        ->postJson('/api/v1/soal/upload-media', ['file' => $file])
        ->assertStatus(422);
});

// ═══ Authorization ════════════════════════════════════════════

test('Peserta cannot access soal endpoints → 403', function () {
    $login = $this->postJson('/api/v1/auth/login', [
        'nik' => '3201010101010001',
        'no_agenda' => 'A001',
    ]);
    $pesertaToken = $login->json('data.token');

    $this->withToken($pesertaToken)->getJson('/api/v1/soal')->assertStatus(403);
    $this->withToken($pesertaToken)->postJson('/api/v1/soal', [])->assertStatus(403);
    $this->withToken($pesertaToken)->postJson('/api/v1/soal/upload-media', [])->assertStatus(403);
});
