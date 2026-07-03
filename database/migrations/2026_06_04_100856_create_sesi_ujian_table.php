<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_ujian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_ujian_id')->constrained('jadwal_ujian')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('waktu_mulai')->nullable();
            $table->timestamp('waktu_batas')->nullable();
            $table->timestamp('waktu_selesai')->nullable();
            $table->string('status')->default('belum_mulai');
            $table->double('skor_pg')->nullable();
            $table->double('skor_benar_salah')->nullable();
            $table->double('skor_labeling')->nullable();
            $table->double('skor_menjodohkan')->nullable();
            $table->double('skor_total')->nullable();
            $table->boolean('is_lulus')->nullable();
            $table->string('ip_mulai', 45)->nullable();
            $table->text('user_agent_mulai')->nullable();
            $table->unsignedInteger('jumlah_pelanggaran')->default(0);
            $table->timestamps();

            $table->unique(['jadwal_ujian_id', 'user_id']);
            $table->index(['status', 'waktu_batas'], 'idx_status_batas');
            $table->index('user_id', 'idx_sesi_user');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sesi_ujian ADD CONSTRAINT sesi_ujian_status_check CHECK (status IN ('belum_mulai','sedang_berlangsung','selesai','dibatalkan','kadaluarsa'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_ujian');
    }
};
