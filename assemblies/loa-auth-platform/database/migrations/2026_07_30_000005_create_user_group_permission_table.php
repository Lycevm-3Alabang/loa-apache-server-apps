<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_group_permission', function (Blueprint $table) {
            $table->foreignId('user_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->boolean('granted')->default(true);
            $table->primary(['user_group_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_group_permission');
    }
};
