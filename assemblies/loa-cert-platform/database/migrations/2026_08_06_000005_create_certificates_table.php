<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable(false);
            $table->uuid('event_id')->nullable()->cascadeOnDelete();
            $table->uuid('template_id')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_email');
            $table->string('certificate_number');
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->string('file_path')->nullable(); // File path in storage (no base64)
            $table->json('metadata')->nullable(); // Holds rendered_html regeneration cache
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->onDelete('cascade');

            $table->foreign('template_id')
                ->references('id')
                ->on('certificate_templates')
                ->onDelete('set null');

            // Unique constraint for active certificates (MySQL workaround for partial index)
            $table->string('number_active')->storedAs('IF(revoked_at IS NULL, certificate_number, NULL)');
            $table->unique('number_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('certificates');
    }
};