<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ⚠️ GOTCHA PostgreSQL NULL distinct:
     * Unique (sesi_ujian_id, soal_id, opsi_id) tidak mencegah duplikat
     * untuk benar_salah karena opsi_id = NULL dan PostgreSQL memperlakukan
     * NULL sebagai distinct. Mitigasi: upsert app-level by (sesi_ujian_id, soal_id)
     * di ScoringService (M10). Index tetap dibuat untuk pg/labeling/menjodohkan.
     */
    public function up(): void
    {
        Schema::create('jawaban', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_ujian_id')->constrained('sesi_ujian')->cascadeOnDelete();
            $table->foreignId('soal_id')->constrained('soal')->restrictOnDelete();
            $table->foreignId('opsi_id')->nullable()->constrained('opsi_soal');
            $table->boolean('jawaban_bool')->nullable();
            $table->unsignedSmallInteger('nomor_jawaban')->nullable();
            $table->foreignId('pasangan_opsi_id')->nullable()->constrained('opsi_soal');
            $table->boolean('is_benar')->nullable();
            $table->double('poin_didapat')->nullable();
            $table->timestamp('waktu_jawab')->useCurrent();
            $table->timestamps();

            $table->unique(['sesi_ujian_id', 'soal_id', 'opsi_id']);
            $table->index('sesi_ujian_id', 'idx_jawaban_sesi');
            $table->index('soal_id', 'idx_jawaban_soal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jawaban');
    }
};
