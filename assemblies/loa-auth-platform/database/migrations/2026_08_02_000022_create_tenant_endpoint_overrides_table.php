<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_endpoint_overrides', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('path', 512);
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->enum('level', ['read', 'write', 'admin', 'deny'])->default('read');
            $table->timestamps();

            $table->primary(['user_id', 'tenant_id', 'method', 'path']);
            $table->index(['method', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_endpoint_overrides');
    }
};
