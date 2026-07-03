<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail persetujuan pakta integritas saat peserta memulai sesi.
     * Diisi dari POST /sesi/mulai bila body `persetujuan === true`.
     */
    public function up(): void
    {
        Schema::table('sesi_ujian', function (Blueprint $table) {
            $table->timestamp('persetujuan_at')->nullable()->after('jumlah_pelanggaran');
            $table->string('ip_persetujuan', 45)->nullable()->after('persetujuan_at');
        });
    }

    public function down(): void
    {
        Schema::table('sesi_ujian', function (Blueprint $table) {
            $table->dropColumn(['persetujuan_at', 'ip_persetujuan']);
        });
    }
};
