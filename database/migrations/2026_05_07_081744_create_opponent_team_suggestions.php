<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opponent_team_suggestions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('team_id')->index();           // the user's team that processed this demo
            $table->integer('demo_id')->index();           // the demo it came from
            $table->string('clan_name');                   // team_clan_name from the demo
            $table->json('steamids');                      // array of player steamids seen
            $table->json('player_names');                  // array of player names seen
            $table->string('map')->nullable();             // map the demo was on
            $table->boolean('dismissed')->default(false);  // user dismissed without creating
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('demo_id')->references('id')->on('demos')->cascadeOnDelete();
            // One suggestion per clan per team — upsert on conflict
            $table->unique(['team_id', 'clan_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opponent_team_suggestions');
    }
};