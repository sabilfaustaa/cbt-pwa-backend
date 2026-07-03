<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nama_role')->unique();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_nama_role_check CHECK (nama_role IN ('admin','pengawas','peserta'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
