<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_soal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_ujian_id')->constrained('jadwal_ujian')->cascadeOnDelete();
            $table->foreignId('soal_id')->constrained('soal')->restrictOnDelete();
            $table->unsignedSmallInteger('nomor_urut');
            $table->timestamps();

            $table->unique(['jadwal_ujian_id', 'soal_id']);
            $table->index('jadwal_ujian_id', 'idx_jadwal_soal_jadwal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_soal');
    }
};
