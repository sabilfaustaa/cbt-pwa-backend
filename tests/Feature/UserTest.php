<?php

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    // Lookup role IDs secara dinamis — sequences Postgres bisa bergeser antar test
    $this->roleAdminId = Role::where('nama_role', RoleName::Admin->value)->value('id');
    $this->rolePengawasId = Role::where('nama_role', RoleName::Pengawas->value)->value('id');
    $this->rolePesertaId = Role::where('nama_role', RoleName::Peserta->value)->value('id');

    $this->adminUser = User::where('role_id', $this->roleAdminId)->first();
    $this->pesertaUser = User::where('role_id', $this->rolePesertaId)->first();

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ]);
    $this->token = $login->json('data.token');
});

// ─── List ──────────────────────────────────────────────────

test('GET /api/v1/users returns paginated user list', function () {
    $response = $this->withToken($this->token)->getJson('/api/v1/users');

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    $response->assertJsonStructure(['data', 'meta' => ['pagination']]);
    expect(count($response->json('data')))->toBeGreaterThan(0);
});

test('GET /api/v1/users filter by role', function () {
    $response = $this->withToken($this->token)->getJson('/api/v1/users?role=peserta');

    $response->assertStatus(200);
    $users = $response->json('data');
    foreach ($users as $u) {
        expect($u['role']['nama_role'])->toBe('peserta');
    }
});

test('GET /api/v1/users filter by q (search)', function () {
    $response = $this->withToken($this->token)->getJson('/api/v1/users?q=Administrator');

    $response->assertStatus(200);
    $users = $response->json('data');
    expect(count($users))->toBeGreaterThanOrEqual(1);
    expect($users[0]['nama'])->toBe('Administrator');
});

// ─── Detail ────────────────────────────────────────────────

test('GET /api/v1/users/:id returns user detail', function () {
    $response = $this->withToken($this->token)->getJson("/api/v1/users/{$this->adminUser->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.email', 'admin@cbt.test');
    $response->assertJsonPath('data.role.nama_role', 'admin');
    expect($response->json('data'))->not->toHaveKey('password');
});

test('GET /api/v1/users/:id not found → 404', function () {
    $response = $this->withToken($this->token)->getJson('/api/v1/users/99999');

    $response->assertStatus(404);
});

// ─── Create ────────────────────────────────────────────────

test('POST /api/v1/users creates petugas (admin/pengawas)', function () {
    $response = $this->withToken($this->token)->postJson('/api/v1/users', [
        'role_id' => $this->rolePengawasId,
        'nama' => 'Pengawas Baru',
        'email' => 'pengawas3@cbt.test',
        'password' => 'password',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.nama', 'Pengawas Baru');
    $response->assertJsonPath('data.email', 'pengawas3@cbt.test');
    $response->assertJsonPath('data.role.nama_role', 'pengawas');
});

test('POST /api/v1/users creates peserta', function () {
    $response = $this->withToken($this->token)->postJson('/api/v1/users', [
        'role_id' => $this->rolePesertaId,
        'nama' => 'Peserta Baru',
        'nik' => '3201020202020001',
        'no_agenda' => 'B001',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.nik', '3201020202020001');
    $response->assertJsonPath('data.no_agenda', 'B001');
    $response->assertJsonPath('data.email', null);
});

test('POST /api/v1/users email duplikat → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/users', [
        'role_id' => $this->rolePengawasId,
        'nama' => 'Duplikat',
        'email' => 'admin@cbt.test',
        'password' => 'password',
    ])->assertStatus(422);
});

test('POST /api/v1/users NIK duplikat → 422', function () {
    $this->withToken($this->token)->postJson('/api/v1/users', [
        'role_id' => $this->rolePesertaId,
        'nama' => 'Duplikat NIK',
        'nik' => '3201010101010001',
        'no_agenda' => 'B003',
    ])->assertStatus(422);
});

test('POST /api/v1/users peserta dengan email → 422 (prohibited)', function () {
    $this->withToken($this->token)->postJson('/api/v1/users', [
        'role_id' => $this->rolePesertaId,
        'nama' => 'Peserta Email',
        'email' => 'peserta@test.com',
        'password' => 'password',
    ])->assertStatus(422);
});

// ─── Update ────────────────────────────────────────────────

test('PATCH /api/v1/users/:id updates user partial', function () {
    $response = $this->withToken($this->token)->patchJson("/api/v1/users/{$this->pesertaUser->id}", [
        'nama' => 'Peserta Diupdate',
        'is_active' => false,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.nama', 'Peserta Diupdate');
    $response->assertJsonPath('data.is_active', false);
});

// ─── Delete ────────────────────────────────────────────────

test('DELETE /api/v1/users/:id deletes user without sesi', function () {
    $create = $this->withToken($this->token)->postJson('/api/v1/users', [
        'role_id' => $this->rolePesertaId,
        'nama' => 'Untuk Dihapus',
        'nik' => '3201030303030001',
        'no_agenda' => 'C001',
    ]);
    $id = $create->json('data.id');

    $response = $this->withToken($this->token)->deleteJson("/api/v1/users/{$id}");

    $response->assertStatus(200);
    $response->assertJsonPath('meta.message', 'User berhasil dihapus.');

    $this->withToken($this->token)->getJson("/api/v1/users/{$id}")->assertStatus(404);
});

test('DELETE /api/v1/users/:id with jadwal → 409', function () {
    // Peserta yang sudah di-assign ke jadwal melalui seeder
    $pesertaDenganJadwal = User::where('role_id', $this->rolePesertaId)->first();

    $response = $this->withToken($this->token)->deleteJson("/api/v1/users/{$pesertaDenganJadwal->id}");

    $response->assertStatus(409);
});

// ─── Bulk Import ───────────────────────────────────────────

test('POST /api/v1/users/bulk-import-peserta imports CSV', function () {
    $csv = "nama,nik,no_agenda\nBudi Import,3201040404040001,D001\nAni Import,3201040404040002,D002\n";

    $file = UploadedFile::fake()->createWithContent('peserta.csv', $csv);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/users/bulk-import-peserta', ['file' => $file]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.imported', 2);
    $response->assertJsonPath('data.failed', 0);

    expect(User::where('nik', '3201040404040001')->exists())->toBeTrue();
    expect(User::where('nik', '3201040404040002')->exists())->toBeTrue();
});

test('POST /api/v1/users/bulk-import-peserta with invalid NIK → partial import', function () {
    $csv = "nama,nik,no_agenda\nValid Import,3201050505050001,E001\nInvalid NIK,123,E002\n";

    $file = UploadedFile::fake()->createWithContent('peserta_partial.csv', $csv);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/users/bulk-import-peserta', ['file' => $file]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.imported', 1);
    $response->assertJsonPath('data.failed', 1);
    expect(count($response->json('data.errors')))->toBe(1);
});

// ─── Authorization ─────────────────────────────────────────

test('Non-admin cannot access user endpoints → 403', function () {
    $login = $this->postJson('/api/v1/auth/login', [
        'nik' => '3201010101010001',
        'no_agenda' => 'A001',
    ]);
    $pesertaToken = $login->json('data.token');

    $this->withToken($pesertaToken)->getJson('/api/v1/users')->assertStatus(403);
    $this->withToken($pesertaToken)->postJson('/api/v1/users', [])->assertStatus(403);
});
