<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->nullable(false);
            $table->uuid('organization_id')->nullable(false);
            $table->string('name');
            $table->string('email')->nullable(false);
            $table->boolean('attended')->default(false);
            $table->boolean('completed')->default(false);
            $table->timestamp('attended_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->uuid('certificate_id')->nullable();
            $table->string('certificate_number')->nullable();
            $table->json('metadata')->nullable(); // holds generation_mode, file_name, file_type, file_path
            $table->timestamps();

            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('certificate_id')
                ->references('id')
                ->on('certificates')
                ->onDelete('set null');

            $table->unique(['event_id', 'email']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_attendees');
    }
};