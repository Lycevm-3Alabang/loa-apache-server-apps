<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_group_permission', function (Blueprint $table) {
            $table->index('user_group_id');
            $table->index('permission_id');
            $table->dropPrimary();
            $table->foreignUuid('tenant_id')->nullable()->after('permission_id')->constrained('tenants')->cascadeOnDelete();
            $table->unique(['user_group_id', 'permission_id', 'tenant_id'], 'ugp_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_group_permission', function (Blueprint $table) {
            $table->dropUnique('ugp_scope_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->primary(['user_group_id', 'permission_id']);
            $table->dropIndex(['user_group_id']);
            $table->dropIndex(['permission_id']);
        });
    }
};
