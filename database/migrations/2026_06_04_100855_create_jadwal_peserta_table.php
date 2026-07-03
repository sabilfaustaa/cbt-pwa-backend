<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_ujian_id')->constrained('jadwal_ujian')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('token_akses', 64)->unique();
            $table->timestamps();

            $table->unique(['jadwal_ujian_id', 'user_id']);
            $table->index('user_id', 'idx_jadwal_peserta_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_peserta');
    }
};
