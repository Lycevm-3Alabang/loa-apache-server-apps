<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            // Last-editor tracking (mirrors certificate_templates.updated_by).
            // Initial value is always identical to created_by; ownership stays
            // created_by-only and is unaffected by this column.
            $table->string('updated_by')->nullable()->after('created_by');
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('updated_by');
        });
    }
};
