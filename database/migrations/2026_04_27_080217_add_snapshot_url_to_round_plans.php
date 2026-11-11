<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Switch round_plans snapshot storage from local (snapshot_path)
     * to Bunny CDN (snapshot_url), matching the pattern used by boards.
     */
    public function up(): void
    {
        Schema::table('round_plans', function (Blueprint $table) {
            // Add the Bunny CDN URL column
            $table->string('snapshot_url')->nullable()->after('data');

            // Drop the old local storage path column (if it exists)
            if (Schema::hasColumn('round_plans', 'snapshot_path')) {
                $table->dropColumn('snapshot_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('round_plans', function (Blueprint $table) {
            $table->dropColumn('snapshot_url');
            $table->string('snapshot_path')->nullable();
        });
    }
};