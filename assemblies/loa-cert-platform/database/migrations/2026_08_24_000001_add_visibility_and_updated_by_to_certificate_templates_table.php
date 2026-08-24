<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            // Temporary server default 'public' preserves existing behavior on
            // deploy; the application layer defaults new API-created rows to
            // 'private' (see specs/components/template-visibility.md §4).
            $table->enum('visibility', ['public', 'private'])
                ->default('public')
                ->after('css_content');
            $table->string('updated_by')->nullable()->after('created_by');
        });
    }

    public function down()
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn(['visibility', 'updated_by']);
        });
    }
};
