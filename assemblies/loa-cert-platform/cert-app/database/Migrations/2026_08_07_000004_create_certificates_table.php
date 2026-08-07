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
            $table->uuid('event_id')->nullable();
            $table->uuid('template_id')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_email');
            $table->string('certificate_number');
            $table->dateTime('issued_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->string('file_path')->nullable();
            $table->json('metadata')->nullable();
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

            $table->unique(['event_id', 'recipient_email']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->char('number_active', 36)
                ->generatedAs('IF(revoked_at IS NULL, certificate_number, NULL)')
                ->stored();
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->unique('number_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('certificates');
    }
};
