<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flag a demo as a curated "admin" demo, replacing the separate admin_demos
     * table. legacy_admin_id is a scratch column holding the originating
     * admin_demos.id so the backfill can repoint tactic links with a single
     * collision-safe JOIN; it is kept for audit/rollback.
     */
    public function up(): void
    {
        Schema::table('demos', function (Blueprint $table) {
            if (!Schema::hasColumn('demos', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->index()->after('owner');
            }
            if (!Schema::hasColumn('demos', 'legacy_admin_id')) {
                $table->unsignedBigInteger('legacy_admin_id')->nullable()->index()->after('is_admin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('demos', function (Blueprint $table) {
            if (Schema::hasColumn('demos', 'legacy_admin_id')) {
                $table->dropColumn('legacy_admin_id');
            }
            if (Schema::hasColumn('demos', 'is_admin')) {
                $table->dropColumn('is_admin');
            }
        });
    }
};
