<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->after('id')->constrained('roles')->restrictOnDelete();
            $table->string('nik', 32)->nullable()->unique()->after('email');
            $table->string('no_agenda', 64)->nullable()->after('nik');
            $table->boolean('is_active')->default(true)->after('no_agenda');
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->index(['nik', 'no_agenda'], 'idx_nik_noagenda');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_nik_noagenda');
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'nik', 'no_agenda', 'is_active']);
            $table->dropSoftDeletes();
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
