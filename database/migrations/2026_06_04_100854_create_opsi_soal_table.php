<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opsi_soal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soal_id')->constrained('soal')->cascadeOnDelete();
            $table->text('teks');
            $table->text('pasangan')->nullable();
            $table->unsignedSmallInteger('nomor_urut')->nullable();
            $table->boolean('is_kunci')->default(false);
            $table->timestamps();

            $table->index('soal_id', 'idx_opsi_soal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opsi_soal');
    }
};
