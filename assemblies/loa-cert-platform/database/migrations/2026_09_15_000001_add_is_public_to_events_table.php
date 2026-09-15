<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            // Visibility flag: false = private (author + cert-admin only),
            // true = public (all cert staff). Defaults to private so existing
            // rows stay author-scoped on deploy.
            $table->boolean('is_public')->default(false)->after('status');
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
