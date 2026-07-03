<?php

use App\Enums\RoleName;
use App\Models\JadwalPeserta;
use App\Models\JadwalSoal;
use App\Models\JadwalUjian;
use App\Models\Role;
use App\Models\SesiUjian;
use App\Models\Soal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ]);
    $this->token = $login->json('data.token');
});

function adminAuth(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

// ═══ List ════════════════════════════════════════════════════

test('GET /api/v1/jadwal-ujian returns paginated list', function () {
    $res = $this->withToken($this->token)->getJson('/api/v1/jadwal-ujian');

    $res->assertStatus(200);
    $res->assertJsonStructure(['data', 'meta' => ['pagination']]);
    expect(count($res->json('data')))->toBeGreaterThanOrEqual(1);
});

test('GET /api/v1/jadwal-ujian filter by status', function () {
    $res = $this->withToken($this->token)->getJson('/api/v1/jadwal-ujian?status=terbuka');

    $res->assertStatus(200);
    $items = $res->json('data');
    foreach ($items as $j) {
        expect($j['status'])->toBe('terbuka');
    }
});

test('GET /api/v1/jadwal-ujian filter by q', function () {
    // Seeder jadwal memakai prefix kode_jadwal 'UAS-2026-*'
    $res = $this->withToken($this->token)->getJson('/api/v1/jadwal-ujian?q=UAS');

    $res->assertStatus(200);
    expect(count($res->json('data')))->toBeGreaterThanOrEqual(1);
});

// ═══ Create ═══════════════════════════════════════════════════

test('POST /api/v1/jadwal-ujian creates jadwal', function () {
    $res = $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian', [
        'kode_jadwal' => 'TEST-2026',
        'nama_ujian' => 'Ujian Test',
        'waktu_mulai' => '2026-06-10T08:00:00.000Z',
        'waktu_selesai' => '2026-06-10T17:00:00.000Z',
        'durasi_menit' => 120,
        'passing_grade' => 70,
    ]);

    $res->assertStatus(201);
    $res->assertJsonPath('data.kode_jadwal', 'TEST-2026');
    $res->assertJsonPath('data.status', 'draft');
    $res->assertJsonPath('data.passing_grade', 70);
});

test('POST /api/v1/jadwal-ujian kode_jadwal not uppercase → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian', [
        'kode_jadwal' => 'test-2026',
        'nama_ujian' => 'Test',
        'waktu_mulai' => '2026-06-10T08:00:00.000Z',
        'waktu_selesai' => '2026-06-10T17:00:00.000Z',
        'durasi_menit' => 120,
        'passing_grade' => 70,
    ])->assertStatus(422);
});

test('POST /api/v1/jadwal-ujian kode_jadwal duplikat → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian', [
        'kode_jadwal' => 'DUP-2026',
        'nama_ujian' => 'First',
        'waktu_mulai' => '2026-06-10T08:00:00.000Z',
        'waktu_selesai' => '2026-06-10T17:00:00.000Z',
        'durasi_menit' => 60,
        'passing_grade' => 70,
    ])->assertStatus(201);

    $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian', [
        'kode_jadwal' => 'DUP-2026',
        'nama_ujian' => 'Second',
        'waktu_mulai' => '2026-06-10T08:00:00.000Z',
        'waktu_selesai' => '2026-06-10T17:00:00.000Z',
        'durasi_menit' => 60,
        'passing_grade' => 70,
    ])->assertStatus(422);
});

test('POST /api/v1/jadwal-ujian durasi > selisih waktu → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian', [
        'kode_jadwal' => 'DUR-FAIL',
        'nama_ujian' => 'Fail',
        'waktu_mulai' => '2026-06-10T08:00:00.000Z',
        'waktu_selesai' => '2026-06-10T09:00:00.000Z', // 60 menit
        'durasi_menit' => 120, // > 60
        'passing_grade' => 70,
    ])->assertStatus(422);
});

test('POST /api/v1/jadwal-ujian passing_grade out of range → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian', [
        'kode_jadwal' => 'GRD-101',
        'nama_ujian' => 'Fail',
        'waktu_mulai' => '2026-06-10T08:00:00.000Z',
        'waktu_selesai' => '2026-06-10T17:00:00.000Z',
        'durasi_menit' => 60,
        'passing_grade' => 101,
    ])->assertStatus(422);
});

// ═══ Detail ═══════════════════════════════════════════════════

test('GET /api/v1/jadwal-ujian/:id returns detail', function () {
    $create = $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian', [
        'kode_jadwal' => 'DET-2026',
        'nama_ujian' => 'Detail Test',
        'waktu_mulai' => '2026-06-10T08:00:00.000Z',
        'waktu_selesai' => '2026-06-10T17:00:00.000Z',
        'durasi_menit' => 60,
        'passing_grade' => 75,
    ]);
    $id = $create->json('data.id');

    $res = $this->withToken($this->token)->getJson("/api/v1/jadwal-ujian/{$id}");

    $res->assertStatus(200);
    $res->assertJsonPath('data.kode_jadwal', 'DET-2026');
});

// ═══ Update ═══════════════════════════════════════════════════

test('PATCH /api/v1/jadwal-ujian/:id updates jadwal', function () {
    $create = $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian', [
        'kode_jadwal' => 'UPD-2026',
        'nama_ujian' => 'Before',
        'waktu_mulai' => '2026-06-10T08:00:00.000Z',
        'waktu_selesai' => '2026-06-10T17:00:00.000Z',
        'durasi_menit' => 60,
        'passing_grade' => 75,
    ]);
    $id = $create->json('data.id');

    $res = $this->withToken($this->token)->patchJson("/api/v1/jadwal-ujian/{$id}", [
        'nama_ujian' => 'After Update',
    ]);

    $res->assertStatus(200);
    $res->assertJsonPath('data.nama_ujian', 'After Update');
});

// ═══ Delete ═══════════════════════════════════════════════════

test('DELETE /api/v1/jadwal-ujian/:id deletes jadwal without sesi', function () {
    $create = $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian', [
        'kode_jadwal' => 'DEL-2026',
        'nama_ujian' => 'To Delete',
        'waktu_mulai' => '2026-06-10T08:00:00.000Z',
        'waktu_selesai' => '2026-06-10T17:00:00.000Z',
        'durasi_menit' => 60,
        'passing_grade' => 75,
    ]);
    $id = $create->json('data.id');

    $this->withToken($this->token)->deleteJson("/api/v1/jadwal-ujian/{$id}")->assertStatus(200);
    $this->withToken($this->token)->getJson("/api/v1/jadwal-ujian/{$id}")->assertStatus(404);
});

test('DELETE /api/v1/jadwal-ujian/:id with sesi → 409', function () {
    // Jadwal id 1 dari seeder sudah punya sesi
    $this->withToken($this->token)->deleteJson('/api/v1/jadwal-ujian/1')->assertStatus(409);
});

// ═══ Status Transition ════════════════════════════════════════

test('PATCH /api/v1/jadwal-ujian/:id/status draft → terbuka', function () {
    $create = $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian', [
        'kode_jadwal' => 'STA-2026',
        'nama_ujian' => 'Status Test',
        'waktu_mulai' => '2026-06-10T08:00:00.000Z',
        'waktu_selesai' => '2026-06-10T17:00:00.000Z',
        'durasi_menit' => 60,
        'passing_grade' => 75,
    ]);
    $id = $create->json('data.id');

    $res = $this->withToken($this->token)->patchJson("/api/v1/jadwal-ujian/{$id}/status", [
        'status' => 'terbuka',
    ]);

    $res->assertStatus(200);
    $res->assertJsonPath('data.status', 'terbuka');
});

test('PATCH /api/v1/jadwal-ujian/:id/status selesai → terbuka invalid', function () {
    // Buat jadwal baru (draft) lalu jalankan state machine: draft→terbuka→berlangsung→selesai
    $create = $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian', [
        'kode_jadwal' => 'SM-2026',
        'nama_ujian' => 'State Machine Test',
        'waktu_mulai' => '2026-06-10T08:00:00.000Z',
        'waktu_selesai' => '2026-06-10T17:00:00.000Z',
        'durasi_menit' => 60,
        'passing_grade' => 70,
    ]);
    $id = $create->json('data.id');

    $this->withToken($this->token)->patchJson("/api/v1/jadwal-ujian/{$id}/status", [
        'status' => 'terbuka',
    ])->assertStatus(200);
    $this->withToken($this->token)->patchJson("/api/v1/jadwal-ujian/{$id}/status", [
        'status' => 'berlangsung',
    ])->assertStatus(200);
    $this->withToken($this->token)->patchJson("/api/v1/jadwal-ujian/{$id}/status", [
        'status' => 'selesai',
    ])->assertStatus(200);

    // Coba balik ke terbuka dari status final → 409
    $this->withToken($this->token)->patchJson("/api/v1/jadwal-ujian/{$id}/status", [
        'status' => 'terbuka',
    ])->assertStatus(409);
});

// ═══ Attach Soal ══════════════════════════════════════════════

test('POST /api/v1/jadwal-ujian/:id/attach-soal attaches new soal', function () {
    $soal = Soal::factory()->create(['tipe' => 'pg']);

    $res = $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian/1/attach-soal', [
        'soal_ids' => [$soal->id],
    ]);

    $res->assertStatus(201);
    $res->assertJsonPath('data.attached', 1);
});

test('POST /api/v1/jadwal-ujian/:id/attach-soal skips duplicates', function () {
    $soal = Soal::factory()->create(['tipe' => 'pg']);

    // Attach pertama
    $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian/1/attach-soal', [
        'soal_ids' => [$soal->id],
    ])->assertStatus(201);

    // Attach lagi — harus skip (attached=0)
    $res = $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian/1/attach-soal', [
        'soal_ids' => [$soal->id],
    ]);

    $res->assertStatus(201);
    $res->assertJsonPath('data.attached', 0);
});

// ═══ Reorder Soal ═════════════════════════════════════════════

test('PUT /api/v1/jadwal-ujian/:id/urutan-soal reorders', function () {
    $soal = Soal::factory()->create(['tipe' => 'pg']);
    $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian/1/attach-soal', [
        'soal_ids' => [$soal->id],
    ]);

    $res = $this->withToken($this->token)->putJson('/api/v1/jadwal-ujian/1/urutan-soal', [
        'items' => [
            ['soal_id' => $soal->id, 'nomor_urut' => 99],
        ],
    ]);

    $res->assertStatus(200);
    $res->assertJsonPath('data.reordered', 1);
});

// ═══ Peserta ══════════════════════════════════════════════════

test('GET /api/v1/jadwal-ujian/:id/peserta returns peserta list', function () {
    $res = $this->withToken($this->token)->getJson('/api/v1/jadwal-ujian/1/peserta');

    $res->assertStatus(200);
    $items = $res->json('data');
    expect(count($items))->toBeGreaterThanOrEqual(1);
    expect($items[0])->toHaveKey('token_akses');
    expect($items[0])->toHaveKey('user');
});

// ═══ Assign Peserta ═══════════════════════════════════════════

test('POST /api/v1/jadwal-ujian/:id/assign-peserta assigns new peserta', function () {
    // Seeder sudah meng-assign peserta id 4-13 ke jadwal 1.
    // Buat 2 peserta baru untuk di-assign.
    $pesertaRoleId = Role::where('nama_role', RoleName::Peserta->value)->value('id');
    $p1 = User::factory()->create(['role_id' => $pesertaRoleId, 'nik' => '3201090909090001', 'no_agenda' => 'Z001', 'password' => null, 'email' => null]);
    $p2 = User::factory()->create(['role_id' => $pesertaRoleId, 'nik' => '3201090909090002', 'no_agenda' => 'Z002', 'password' => null, 'email' => null]);

    $res = $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian/1/assign-peserta', [
        'user_ids' => [$p1->id, $p2->id],
    ]);

    $res->assertStatus(201);
    expect($res->json('data.assigned'))->toBe(2);
    expect($res->json('data.failed'))->toBe(0);
    expect(count($res->json('data.tokens')))->toBe(2);
    // Token 64 karakter hex
    expect(strlen($res->json('data.tokens.0.token_akses')))->toBe(64);
});

test('POST /api/v1/jadwal-ujian/:id/assign-peserta idempotent', function () {
    // Assign peserta yang sudah ter-assign (id 4-13 via seeder)
    $res = $this->withToken($this->token)->postJson('/api/v1/jadwal-ujian/1/assign-peserta', [
        'user_ids' => [4, 5], // 4 & 5 sudah ter-assign
    ]);

    $res->assertStatus(201);
    expect($res->json('data.assigned'))->toBe(0);
    expect($res->json('data.failed'))->toBe(2);
});

// ═══ Unassign Peserta (M-B1) ══════════════════════════════════

test('DELETE /api/v1/jadwal-ujian/:id/assign-peserta/:userId melepas peserta belum mulai', function () {
    // Jadwal BIND (terbuka) — semua peserta belum_mulai
    $jadwal = JadwalUjian::where('kode_jadwal', 'UAS-2026-BIND')->first();
    $peserta = JadwalPeserta::where('jadwal_ujian_id', $jadwal->id)->first();

    $res = $this->withToken($this->token)
        ->deleteJson("/api/v1/jadwal-ujian/{$jadwal->id}/assign-peserta/{$peserta->user_id}");

    $res->assertStatus(200);
    expect(JadwalPeserta::where('jadwal_ujian_id', $jadwal->id)->where('user_id', $peserta->user_id)->exists())->toBeFalse();
    // Sesi belum_mulai ikut terhapus
    expect(SesiUjian::where('jadwal_ujian_id', $jadwal->id)->where('user_id', $peserta->user_id)->exists())->toBeFalse();
});

test('DELETE assign-peserta peserta yang sudah ujian (selesai) → 422', function () {
    // Jadwal TIK semua peserta selesai
    $jadwal = JadwalUjian::where('kode_jadwal', 'UAS-2026-TIK')->first();
    $peserta = JadwalPeserta::where('jadwal_ujian_id', $jadwal->id)->first();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/jadwal-ujian/{$jadwal->id}/assign-peserta/{$peserta->user_id}")
        ->assertStatus(422);
});

test('DELETE assign-peserta peserta tidak terdaftar → 404', function () {
    $jadwal = JadwalUjian::where('kode_jadwal', 'UAS-2026-BIND')->first();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/jadwal-ujian/{$jadwal->id}/assign-peserta/999999")
        ->assertStatus(404);
});

test('DELETE assign-peserta pengawas → 403', function () {
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'pengawas1@cbt.test',
        'password' => 'password',
    ]);
    $pengawasToken = $login->json('data.token');
    $jadwal = JadwalUjian::where('kode_jadwal', 'UAS-2026-BIND')->first();
    $peserta = JadwalPeserta::where('jadwal_ujian_id', $jadwal->id)->first();

    $this->withToken($pengawasToken)
        ->deleteJson("/api/v1/jadwal-ujian/{$jadwal->id}/assign-peserta/{$peserta->user_id}")
        ->assertStatus(403);
});

// ═══ Detach Soal (M-B1) ═══════════════════════════════════════

test('DELETE /api/v1/jadwal-ujian/:id/soal/:soalId melepas soal pada jadwal terbuka', function () {
    $jadwal = JadwalUjian::where('kode_jadwal', 'UAS-2026-BIND')->first();
    $js = JadwalSoal::where('jadwal_ujian_id', $jadwal->id)->orderBy('nomor_urut')->first();

    $res = $this->withToken($this->token)
        ->deleteJson("/api/v1/jadwal-ujian/{$jadwal->id}/soal/{$js->soal_id}");

    $res->assertStatus(200);
    expect(JadwalSoal::where('jadwal_ujian_id', $jadwal->id)->where('soal_id', $js->soal_id)->exists())->toBeFalse();

    // nomor_urut tetap kontigu (1..N)
    $urutList = JadwalSoal::where('jadwal_ujian_id', $jadwal->id)->orderBy('nomor_urut')->pluck('nomor_urut')->all();
    expect($urutList)->toBe(range(1, count($urutList)));
});

test('DELETE soal pada jadwal berlangsung → 422', function () {
    $jadwal = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->first();
    $js = JadwalSoal::where('jadwal_ujian_id', $jadwal->id)->first();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/jadwal-ujian/{$jadwal->id}/soal/{$js->soal_id}")
        ->assertStatus(422);
});

test('DELETE soal tidak terpasang → 404', function () {
    $jadwal = JadwalUjian::where('kode_jadwal', 'UAS-2026-BIND')->first();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/jadwal-ujian/{$jadwal->id}/soal/999999")
        ->assertStatus(404);
});

// ═══ Authorization ════════════════════════════════════════════

test('Peserta cannot access mutasi jadwal → 403', function () {
    $login = $this->postJson('/api/v1/auth/login', [
        'nik' => '3201010101010001',
        'no_agenda' => 'A001',
    ]);
    $pesertaToken = $login->json('data.token');

    $this->withToken($pesertaToken)->postJson('/api/v1/jadwal-ujian', [])->assertStatus(403);
    $this->withToken($pesertaToken)->patchJson('/api/v1/jadwal-ujian/1/status', [])->assertStatus(403);
});

test('Peserta cannot access jadwal list → 403', function () {
    $login = $this->postJson('/api/v1/auth/login', [
        'nik' => '3201010101010001',
        'no_agenda' => 'A001',
    ]);
    $pesertaToken = $login->json('data.token');

    $this->withToken($pesertaToken)->getJson('/api/v1/jadwal-ujian')->assertStatus(403);
});
