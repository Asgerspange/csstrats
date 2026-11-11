<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // opponent_teams.id → unsigned int (matches teams.id style)
        Schema::create('opponent_teams', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('team_id')->index();                    // FK → teams.id (int)
            $table->string('name');                                 // e.g. "noobteam123"
            $table->string('tag', 32)->nullable();
            $table->text('notes')->nullable();
            $table->string('platform_url')->nullable();
            $table->json('ai_insights')->nullable();
            $table->timestamp('insights_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });

        // Pivot: which demos belong to which opponent profile
        // demos.id → int, opponent_teams.id → unsigned int
        Schema::create('opponent_team_demos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('opponent_team_id')->index();   // FK → opponent_teams.id (unsigned int)
            $table->integer('demo_id')->index();                    // FK → demos.id (int)
            $table->timestamps();

            $table->foreign('opponent_team_id')->references('id')->on('opponent_teams')->cascadeOnDelete();
            $table->foreign('demo_id')->references('id')->on('demos')->cascadeOnDelete();
            $table->unique(['opponent_team_id', 'demo_id']);
        });

        // Known players on each opponent team (auto-populated from demo parsing)
        Schema::create('opponent_team_players', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('opponent_team_id')->index();   // FK → opponent_teams.id (unsigned int)
            $table->string('steamid', 32)->index();
            $table->string('name');
            $table->string('alias')->nullable();
            $table->text('notes')->nullable();
            $table->json('tendency_cache')->nullable();
            $table->timestamps();

            $table->foreign('opponent_team_id')->references('id')->on('opponent_teams')->cascadeOnDelete();
            $table->unique(['opponent_team_id', 'steamid']);
        });

        // Counter-strats: map_id → maps.id (bigint unsigned)
        Schema::create('opponent_counter_strats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('opponent_team_id')->index();   // FK → opponent_teams.id (unsigned int)
            $table->integer('team_id')->index();                    // FK → teams.id (int)
            $table->string('name');
            $table->unsignedBigInteger('map_id')->index();          // FK → maps.id (bigint unsigned)
            $table->string('side', 2);                             // T | CT
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->integer('demo_id')->nullable();                 // FK → demos.id (int)
            $table->integer('demo_round')->nullable();
            $table->integer('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('opponent_team_id')->references('id')->on('opponent_teams')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('map_id')->references('id')->on('maps')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opponent_counter_strats');
        Schema::dropIfExists('opponent_team_players');
        Schema::dropIfExists('opponent_team_demos');
        Schema::dropIfExists('opponent_teams');
    }
};