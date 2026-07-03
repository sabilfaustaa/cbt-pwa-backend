<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->text('isi');
            $table->string('penulis');
            $table->boolean('is_penting')->default(false);
            $table->foreignId('jadwal_id')->nullable()
                ->constrained('jadwal_ujian')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('published_at', 'idx_pengumuman_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
