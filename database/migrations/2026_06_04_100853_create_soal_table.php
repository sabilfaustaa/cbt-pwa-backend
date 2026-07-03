<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soal', function (Blueprint $table) {
            $table->id();
            $table->string('tipe');
            $table->text('pertanyaan');
            $table->string('media_url', 512)->nullable();
            $table->unsignedSmallInteger('poin')->default(1);
            $table->boolean('jawaban_benar_bool')->nullable();
            $table->text('pembahasan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tipe', 'idx_tipe');
            $table->index('created_by', 'idx_soal_creator');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE soal ADD CONSTRAINT soal_tipe_check CHECK (tipe IN ('pg','benar_salah','labeling','menjodohkan'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('soal');
    }
};
