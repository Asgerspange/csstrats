<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-defined zones per map (canonical ground truth)
        Schema::create('map_callout_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('map_id');
            $table->string('canonical_name');          // e.g. "Jungle", "B Main"
            $table->string('short_code', 20)->nullable(); // e.g. "JNG", "BMAIN" for auto-tagging
            $table->json('polygon');                   // [[x,y],[x,y],...] normalised 0-1000
            $table->string('color', 7)->default('#f97316'); // hex display color
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('map_id')->references('id')->on('maps')->onDelete('cascade');
            $table->index('map_id');
        });

        // Per-team custom callout names (teams rename zones but can't add/delete)
        Schema::create('team_callout_names', function (Blueprint $table) {
            $table->id();
            $table->integer('team_id');
            $table->unsignedBigInteger('zone_id');
            $table->string('custom_name');             // Team's name for this zone
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('zone_id')->references('id')->on('map_callout_zones')->onDelete('cascade');
            $table->unique(['team_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_callout_names');
        Schema::dropIfExists('map_callout_zones');
    }
};