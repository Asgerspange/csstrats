<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move opponent-team ownership from a team to the user who created it, and
 * introduce an opponent_team_shares pivot so an owner can share a scouting
 * profile with specific teams they belong to.
 *
 * Existing opponent_teams are backfilled to their origin team's owner and
 * auto-shared back to that team, so nothing loses the visibility it had.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Sharing pivot ─────────────────────────────────────────────────────
        Schema::create('opponent_team_shares', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('opponent_team_id')->index();
            $table->integer('team_id')->index();
            $table->timestamps();

            $table->foreign('opponent_team_id')->references('id')->on('opponent_teams')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->unique(['opponent_team_id', 'team_id']);
        });

        // ── opponent_teams: team_id → user_id ────────────────────────────────
        Schema::table('opponent_teams', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
        });

        // Backfill ownership from the origin team's owner.
        DB::statement('
            UPDATE opponent_teams ot
            JOIN teams t ON t.id = ot.team_id
            SET ot.user_id = t.owned_by
            WHERE ot.user_id IS NULL
        ');

        // Auto-share every existing profile back to its origin team so the
        // whole team keeps the visibility it had before this change.
        DB::statement('
            INSERT INTO opponent_team_shares (opponent_team_id, team_id, created_at, updated_at)
            SELECT id, team_id, NOW(), NOW()
            FROM opponent_teams
            WHERE team_id IS NOT NULL
        ');

        Schema::table('opponent_teams', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });
        Schema::table('opponent_teams', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // ── opponent_team_suggestions: team_id → user_id ─────────────────────
        // Suggestions are transient (regenerated on the next demo parse), so we
        // clear them rather than risk unique collisions when two of a user's
        // teams previously held the same clan suggestion.
        DB::statement('DELETE FROM opponent_team_suggestions');

        Schema::table('opponent_team_suggestions', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropUnique(['team_id', 'clan_name']);
            $table->dropIndex(['team_id']);
            $table->dropColumn('team_id');
        });
        Schema::table('opponent_team_suggestions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->after('id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'clan_name']);
        });

        // ── opponent_counter_strats: team_id becomes optional ────────────────
        // Counter-strats are reached through the opponent profile now, so the
        // team link is no longer the access gate — make it nullable.
        Schema::table('opponent_counter_strats', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });
        Schema::table('opponent_counter_strats', function (Blueprint $table) {
            $table->integer('team_id')->nullable()->change();
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // counter_strats: team_id back to required
        Schema::table('opponent_counter_strats', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });
        Schema::table('opponent_counter_strats', function (Blueprint $table) {
            $table->integer('team_id')->nullable(false)->change();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });

        // suggestions: user_id → team_id (transient data; structural restore only)
        DB::statement('DELETE FROM opponent_team_suggestions');
        Schema::table('opponent_team_suggestions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'clan_name']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
        Schema::table('opponent_team_suggestions', function (Blueprint $table) {
            $table->integer('team_id')->after('id')->index();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->unique(['team_id', 'clan_name']);
        });

        // opponent_teams: user_id → team_id, restored from a share row
        Schema::table('opponent_teams', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->integer('team_id')->nullable()->after('id')->index();
        });
        DB::statement('
            UPDATE opponent_teams ot
            JOIN (
                SELECT opponent_team_id, MIN(team_id) AS team_id
                FROM opponent_team_shares
                GROUP BY opponent_team_id
            ) s ON s.opponent_team_id = ot.id
            SET ot.team_id = s.team_id
        ');
        Schema::table('opponent_teams', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });

        Schema::dropIfExists('opponent_team_shares');
    }
};
