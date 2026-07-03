<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_ujian', function (Blueprint $table) {
            $table->id();
            $table->string('kode_jadwal', 64)->unique();
            $table->string('nama_ujian');
            $table->text('deskripsi')->nullable();
            $table->timestamp('waktu_mulai');
            $table->timestamp('waktu_selesai');
            $table->unsignedInteger('durasi_menit');
            $table->boolean('acak_soal')->default(false);
            $table->boolean('acak_opsi')->default(false);
            $table->boolean('tampilkan_hasil')->default(true);
            $table->unsignedSmallInteger('passing_grade')->default(75);
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'waktu_mulai'], 'idx_status_waktu');
            $table->index('created_by', 'idx_jadwal_creator');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE jadwal_ujian ADD CONSTRAINT jadwal_ujian_status_check CHECK (status IN ('draft','terbuka','berlangsung','selesai','dibatalkan'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_ujian');
    }
};
