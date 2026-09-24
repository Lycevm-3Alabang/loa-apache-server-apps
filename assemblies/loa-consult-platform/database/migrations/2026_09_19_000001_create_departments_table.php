<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('dean_id')->nullable(); // opaque Auth sub
            $table->boolean('is_disabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
