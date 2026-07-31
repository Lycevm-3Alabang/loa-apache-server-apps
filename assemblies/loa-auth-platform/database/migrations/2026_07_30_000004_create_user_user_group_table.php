<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_user_group', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_group_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->primary(['user_id', 'user_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_user_group');
    }
};
