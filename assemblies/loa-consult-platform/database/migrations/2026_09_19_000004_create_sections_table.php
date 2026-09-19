<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('program');
            $table->foreignId('department_course_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_disabled')->default(false);
            $table->unique(['name', 'program']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
