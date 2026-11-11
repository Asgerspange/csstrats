<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds map_id to stratbooks so auto-provisioned stratbooks are linked to
     * a specific map. Legacy stratbooks keep map_id = null.
     *
     * The unique index on (team_id, map_id) only enforces 1:1 for map_based
     * teams because MySQL treats multiple NULLs as distinct — legacy
     * stratbooks (map_id = null) are unconstrained.
     */
    public function up(): void
    {
        Schema::table('stratbooks', function (Blueprint $table) {
            $table->unsignedBigInteger('map_id')->nullable()->after('created_by');
            $table->unique(['team_id', 'map_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stratbooks', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'map_id']);
            $table->dropColumn('map_id');
        });
    }
};
