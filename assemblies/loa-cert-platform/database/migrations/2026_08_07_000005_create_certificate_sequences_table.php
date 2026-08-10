<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('certificate_sequences', function (Blueprint $table) {
            $table->uuid('organization_id');
            $table->string('pattern');
            $table->integer('next_value')->default(1);
            $table->timestamps();

            $table->primary(['organization_id', 'pattern']);

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('certificate_sequences');
    }
};
