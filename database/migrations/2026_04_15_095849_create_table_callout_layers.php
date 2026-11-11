<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Layer definitions per map ───────────────────────────────────────
        // Each map has its own set of layers (Base, Sub-area, Point, etc.)
        Schema::create('callout_layers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('map_id');
            $table->string('name');               // e.g. "Base Areas", "Sub-Areas", "Point Callouts"
            $table->integer('order')->default(0); // render order — higher = on top of lower layers
            $table->string('color', 7)->nullable(); // optional tint for all zones in this layer
            $table->timestamps();

            $table->foreign('map_id')->references('id')->on('maps')->onDelete('cascade');
            $table->index('map_id');
        });

        // ── Update map_callout_zones ───────────────────────────────────────
        Schema::table('map_callout_zones', function (Blueprint $table) {
            // Layer assignment — nullable so existing zones aren't broken
            $table->unsignedBigInteger('layer_id')->nullable()->after('map_id');

            // Zone type — drives auto-tagging priority in the demo viewer
            // 'area'    = large region (CT Spawn, T Spawn) — lowest priority
            // 'zone'    = named sub-area (Jungle, Short, Ticket) — medium priority
            // 'point'   = pixel-precise spot (corner, boost, pixel) — highest priority
            $table->enum('zone_type', ['area', 'zone', 'point'])->default('zone')->after('layer_id');

            $table->foreign('layer_id')->references('id')->on('callout_layers')->onDelete('set null');
            $table->index('layer_id');
        });

        // ── Seed default layers for all existing maps ─────────────────────
        $maps = DB::table('maps')->where('active', true)->get(['id']);
        foreach ($maps as $map) {
            DB::table('callout_layers')->insert([
                ['map_id' => $map->id, 'name' => 'Base Areas',     'order' => 0, 'color' => null, 'created_at' => now(), 'updated_at' => now()],
                ['map_id' => $map->id, 'name' => 'Sub-Areas',      'order' => 1, 'color' => null, 'created_at' => now(), 'updated_at' => now()],
                ['map_id' => $map->id, 'name' => 'Point Callouts', 'order' => 2, 'color' => null, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('map_callout_zones', function (Blueprint $table) {
            $table->dropForeign(['layer_id']);
            $table->dropColumn(['layer_id', 'zone_type']);
        });
        Schema::dropIfExists('callout_layers');
    }
};