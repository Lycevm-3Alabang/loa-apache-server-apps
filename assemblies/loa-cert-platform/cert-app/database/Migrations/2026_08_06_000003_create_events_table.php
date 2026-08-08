<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable(false);
            $table->uuid('template_id')->nullable();
            $table->uuid('email_template_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('event_date')->nullable();
            $table->string('location')->nullable();
            $table->string('organizer')->nullable();
            $table->string('certificate_title')->default('Certificate of Participation');
            $table->string('certificate_number_pattern'); // user-configurable pattern with ####
            $table->date('valid_until')->nullable();
            $table->enum('status', ['draft', 'active', 'archive'])->default('draft');
            $table->string('created_by')->nullable(); // opaque Auth sub
            $table->timestamps();
            
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
                
            $table->foreign('template_id')
                ->references('id')
                ->on('certificate_templates')
                ->onDelete('set null');
                
            $table->foreign('email_template_id')
                ->references('id')
                ->on('certificate_templates')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('events');
    }
};