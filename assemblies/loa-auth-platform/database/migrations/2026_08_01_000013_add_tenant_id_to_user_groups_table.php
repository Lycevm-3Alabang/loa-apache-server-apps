<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            $table->dropUnique('user_groups_name_unique');
            $table->foreignUuid('tenant_id')->nullable()->after('description')->constrained('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'name']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->string('name')->unique()->change();
        });
    }
};
