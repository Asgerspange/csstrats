<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opponent_counter_strats', function (Blueprint $table) {
            $table->json('zone_ids')->nullable()->after('tags');
            $table->boolean('ai_generated')->default(false)->after('zone_ids');
        });

        Schema::table('opponent_teams', function (Blueprint $table) {
            $table->timestamp('counter_strats_generated_at')->nullable()->after('insights_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('opponent_counter_strats', function (Blueprint $table) {
            $table->dropColumn(['zone_ids', 'ai_generated']);
        });
        Schema::table('opponent_teams', function (Blueprint $table) {
            $table->dropColumn('counter_strats_generated_at');
        });
    }
};