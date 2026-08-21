<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('dev_app_url')->nullable()->after('app_url');
            $table->json('dev_redirect_origins')->nullable()->after('redirect_origins');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['dev_app_url', 'dev_redirect_origins']);
        });
    }
};
