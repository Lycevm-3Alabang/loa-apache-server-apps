<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_policies', function (Blueprint $table) {
            $table->id();
            $table->string('app');
            $table->string('method');
            $table->string('path');
            $table->string('claim_key');
            $table->string('filter')->default('all');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_policies');
    }
};