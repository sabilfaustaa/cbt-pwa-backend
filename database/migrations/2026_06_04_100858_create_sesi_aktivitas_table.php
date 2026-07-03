<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_ujian_id')->constrained('sesi_ujian')->cascadeOnDelete();
            $table->string('jenis', 32);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('sesi_ujian_id', 'idx_sesi_aktivitas_sesi');
            $table->index('jenis', 'idx_sesi_aktivitas_jenis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_aktivitas');
    }
};
