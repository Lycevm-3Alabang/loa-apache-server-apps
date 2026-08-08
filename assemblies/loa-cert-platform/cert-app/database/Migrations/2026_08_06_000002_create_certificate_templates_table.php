<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable(false);
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['certificate', 'email']);
            $table->longText('html_content');
            $table->text('css_content')->nullable();
            $table->string('created_by')->nullable(); // opaque Auth sub
            $table->timestamps();
            
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
                
            $table->unique(['organization_id', 'name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('certificate_templates');
    }
};