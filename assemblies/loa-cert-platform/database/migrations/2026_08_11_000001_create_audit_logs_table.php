<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable(false);
            $table->string('user_id')->nullable(); // opaque Auth sub
            $table->string('user_email')->nullable();
            $table->string('action');
            $table->string('source')->default('api');
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index(['action', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
};
