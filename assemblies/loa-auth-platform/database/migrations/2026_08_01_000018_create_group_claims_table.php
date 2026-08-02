<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->string('claim_key');
            $table->string('scope_type')->default('none');
            $table->string('scope_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_claims');
    }
};