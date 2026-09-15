<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Authorship required-always (spec event-visibility.md §§2,4):
     * backfill deterministically, then constrain. Raw statements because the
     * project has no doctrine/dbal (->change() unavailable).
     */
    public function up()
    {
        // updated_by initial value is defined identical to created_by.
        DB::table('events')->whereNull('updated_by')->update([
            'updated_by' => DB::raw('created_by'),
        ]);

        DB::table('certificate_templates')->whereNull('updated_by')->update([
            'updated_by' => DB::raw('created_by'),
        ]);

        // Timestamps: Eloquent stamps all new writes; legacy NULLs take the
        // rollout time (approximate — see spec §4 runbook note).
        DB::table('events')
            ->whereNull('created_at')
            ->orWhereNull('updated_at')
            ->update([
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => DB::raw('COALESCE(updated_at, NOW())'),
            ]);

        // Fail loudly on unresolvable authorship BEFORE constraining, so the
        // operator backfills (or deletes the event row) and re-runs.
        if (DB::table('events')->whereNull('created_by')->exists()) {
            throw new \RuntimeException(
                'Migration aborted: events.created_by has NULL rows. ' .
                'Assign the true author sub (or delete the event row) and re-run.'
            );
        }

        if (DB::table('certificate_templates')->whereNull('updated_by')->whereNull('created_by')->exists()) {
            throw new \RuntimeException(
                'Migration aborted: certificate_templates rows exist with NULL created_by, ' .
                'so updated_by cannot be backfilled. Resolve them first and re-run.'
            );
        }

        DB::statement('ALTER TABLE events MODIFY created_by VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE events MODIFY updated_by VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE events MODIFY created_at TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE events MODIFY updated_at TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE certificate_templates MODIFY updated_by VARCHAR(255) NOT NULL');
    }

    public function down()
    {
        // Data backfill is intentionally not reversed.
        DB::statement('ALTER TABLE events MODIFY created_by VARCHAR(255) NULL');
        DB::statement('ALTER TABLE events MODIFY updated_by VARCHAR(255) NULL');
        DB::statement('ALTER TABLE events MODIFY created_at TIMESTAMP NULL DEFAULT NULL');
        DB::statement('ALTER TABLE events MODIFY updated_at TIMESTAMP NULL DEFAULT NULL');
        DB::statement('ALTER TABLE certificate_templates MODIFY updated_by VARCHAR(255) NULL');
    }
};
