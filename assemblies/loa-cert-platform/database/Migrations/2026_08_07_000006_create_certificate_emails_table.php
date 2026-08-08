<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('certificate_emails', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('certificate_id');
            $table->string('sent_to');
            $table->string('subject');
            $table->dateTime('sent_at')->useCurrent();
            $table->string('sent_by')->nullable();
            $table->string('status')->default('sent');
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->foreign('certificate_id')
                ->references('id')
                ->on('certificates')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('certificate_emails');
    }
};
