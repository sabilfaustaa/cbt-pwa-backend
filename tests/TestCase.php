<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Reset semua sequence PostgreSQL setelah RefreshDatabase memulai transaksi.
     *
     * `RefreshDatabase` membungkus tiap test dalam transaction. Di PostgreSQL,
     * sequence TIDAK ikut rollback — sehingga ID antar test bisa berbeda.
     * `setval` adalah non-transactional: efeknya permanen walau dipanggil
     * di dalam transaksi (termasuk setelah rollback).
     *
     * Dengan override ini, sequence selalu mulai dari 1 di tiap test.
     * Order: parent::setUp() → RefreshDatabase starts tx → reset sequences.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetPostgresSequences();
    }

    private function resetPostgresSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'roles', 'users', 'soal', 'opsi_soal',
            'jadwal_ujian', 'jadwal_soal', 'jadwal_peserta',
            'sesi_ujian', 'jawaban', 'audit_log', 'sesi_aktivitas',
            'personal_access_tokens',
        ];

        foreach ($tables as $table) {
            try {
                DB::selectOne(
                    "SELECT setval(pg_get_serial_sequence(?, 'id'), 1, false)",
                    [$table]
                );
            } catch (\Throwable) {
                // Tabel/sequence tidak ada — abaikan
            }
        }
    }
}
