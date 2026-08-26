<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin audit log — admin-audit-log.md §3.
 * Append-only evidence per kernels/audit.md v1.0: no updates, no deletes,
 * no organization concept in this assembly; source defaults to 'web'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('actor_id')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('action');
            $table->string('source')->default('web');
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['action', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
