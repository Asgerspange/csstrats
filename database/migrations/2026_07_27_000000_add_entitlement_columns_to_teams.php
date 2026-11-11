<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the columns that drive the map-based entitlement system.
     *
     * entitlement_mode:   'legacy' for existing teams (old stratbook model),
     *                      'map_based' for new teams (auto-provisioned 1:1 per map).
     * map_lock_expires_at: when the current map selection lock expires (null for legacy).
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->enum('entitlement_mode', ['legacy', 'map_based'])->default('legacy')->after('invite_token');
            $table->timestamp('map_lock_expires_at')->nullable()->after('entitlement_mode');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['entitlement_mode', 'map_lock_expires_at']);
        });
    }
};
