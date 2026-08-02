<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_app_endpoints', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('path', 512);
            $table->string('label', 255)->nullable();
            $table->text('description')->nullable();
            $table->enum('required_level', ['read', 'write', 'admin'])->default('read');
            $table->timestamps();

            $table->primary(['tenant_id', 'method', 'path']);
            $table->index(['method', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_app_endpoints');
    }
};
