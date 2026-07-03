<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom idempotency_key ke tabel jawaban.
     *
     * Frontend PWA mengirim header X-Idempotency-Key = "sesiId:soalId"
     * saat replay offline queue. Disimpan di sini untuk audit & traceability.
     * Penghindaran duplikat utama tetap dilakukan oleh updateOrCreate di app-level
     * (SesiService::upsertJawaban) karena constraint NULL PostgreSQL tidak cover benar_salah.
     */
    public function up(): void
    {
        Schema::table('jawaban', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable()->after('pasangan_opsi_id');
            $table->index('idempotency_key', 'idx_jawaban_idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('jawaban', function (Blueprint $table) {
            $table->dropIndex('idx_jawaban_idempotency_key');
            $table->dropColumn('idempotency_key');
        });
    }
};
