<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot table for a team's selected maps. Used by Starter (4 maps) and
     * Team (6 maps) tiers. Pro teams have no rows here (all maps accessible).
     */
    public function up(): void
    {
        Schema::create('team_map_selection', function (Blueprint $table) {
            $table->unsignedInteger('team_id');
            $table->unsignedBigInteger('map_id');
            $table->timestamps();

            $table->primary(['team_id', 'map_id']);
            $table->foreign('map_id')->references('id')->on('maps')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_map_selection');
    }
};
