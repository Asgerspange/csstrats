<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the round_planner boolean to user_limits so PlanService can gate
     * the Strat Builder (Round Planner) as a Team+ feature. Defaults to
     * false (Starter-equivalent) so a row that predates this migration reads
     * as Starter until a webhook or admin action writes the real tier's value.
     *
     * Also backfills every existing row to the Team tier's limits. All current
     * paying users are on Team, but the earlier max_demos migration (which
     * defaulted to 2, the Starter allowance) and this new round_planner
     * column (defaulting to false) left them under-provisioned. Without this
     * backfill, every existing Team user would hit the upgrade modal on their
     * next demo upload or Strat Builder save.
     */
    public function up(): void
    {
        Schema::table('user_limits', function (Blueprint $table) {
            $table->boolean('round_planner')->default(false)->after('ai_antistrat');
        });

        // Backfill existing rows to Team-equivalent limits. max_members /
        // max_teams / max_stratbooks / ai_antistrat are already correct from
        // the Paddle webhook; only the two newly-added columns need fixing.
        // 100000 is the config/plans.php "effectively unlimited" sentinel.
        DB::table('user_limits')->update([
            'max_demos' => 100000,
            'round_planner' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('user_limits', function (Blueprint $table) {
            $table->dropColumn('round_planner');
        });
    }
};
