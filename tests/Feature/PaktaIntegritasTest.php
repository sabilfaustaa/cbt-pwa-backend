<?php

use App\Models\JadwalPeserta;
use App\Models\JadwalUjian;
use App\Models\SesiUjian;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    // A021 belum mulai pada jadwal MTK (berlangsung & aktif)
    $this->peserta = User::where('no_agenda', 'A021')->first();
    $this->jadwalMTK = JadwalUjian::where('kode_jadwal', 'UAS-2026-MTK')->first();
    $this->token = JadwalPeserta::where('jadwal_ujian_id', $this->jadwalMTK->id)
        ->where('user_id', $this->peserta->id)
        ->value('token_akses');
});

test('POST /sesi/mulai dengan persetujuan:true menyimpan timestamp + IP', function () {
    $res = $this->actingAs($this->peserta, 'sanctum')
        ->postJson('/api/v1/sesi/mulai', [
            'token_akses' => $this->token,
            'persetujuan' => true,
        ]);

    $res->assertStatus(200);

    $sesi = SesiUjian::where('jadwal_ujian_id', $this->jadwalMTK->id)
        ->where('user_id', $this->peserta->id)
        ->first();

    expect($sesi->persetujuan_at)->not->toBeNull();
    expect($sesi->ip_persetujuan)->not->toBeNull();
});

test('POST /sesi/mulai tanpa flag persetujuan tetap jalan (backward compatible)', function () {
    $res = $this->actingAs($this->peserta, 'sanctum')
        ->postJson('/api/v1/sesi/mulai', [
            'token_akses' => $this->token,
        ]);

    $res->assertStatus(200);

    $sesi = SesiUjian::where('jadwal_ujian_id', $this->jadwalMTK->id)
        ->where('user_id', $this->peserta->id)
        ->first();

    expect($sesi->persetujuan_at)->toBeNull();
    expect($sesi->ip_persetujuan)->toBeNull();
});

test('POST /sesi/mulai persetujuan non-boolean → 422', function () {
    $this->actingAs($this->peserta, 'sanctum')
        ->postJson('/api/v1/sesi/mulai', [
            'token_akses' => $this->token,
            'persetujuan' => 'bukan-boolean-xx',
        ])
        ->assertStatus(422);
});
