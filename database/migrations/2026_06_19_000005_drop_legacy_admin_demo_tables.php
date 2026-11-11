<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the legacy admin demo pipeline tables. Their data was backfilled
     * into the unified demos / demo_rounds tables by
     * 2026_06_19_000004_backfill_admin_demos_into_demos.
     *
     * RUN LAST — only after end-to-end verification confirms existing released
     * tactics still render from the unified table. admin_demo_tactics is kept
     * (its demo_id now references demos.id).
     */
    public function up(): void
    {
        // Schema::dropIfExists('admin_queued_demo_chunks');
        // Schema::dropIfExists('admin_queued_demos');
        // Schema::dropIfExists('admin_demo_rounds');
        // Schema::dropIfExists('admin_demos');
        // Schema::dropIfExists('admin_demo_paths');
    }

    /**
     * Irreversible: the legacy schema predates tracked migrations and the data
     * now lives in the unified demos tables. Restore from a DB backup if needed.
     */
    public function down(): void
    {
        // no-op
    }
};
