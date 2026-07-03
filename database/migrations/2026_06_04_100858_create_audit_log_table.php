<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('entity_type', 64)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('action', 'idx_audit_action');
            $table->index('user_id', 'idx_audit_user');
            $table->index(['entity_type', 'entity_id'], 'idx_audit_entity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
