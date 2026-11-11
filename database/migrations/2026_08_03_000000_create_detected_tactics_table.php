<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detected_tactics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('demo_id');
            $table->foreign('demo_id')->references('id')->on('demos')->cascadeOnDelete();
            $table->unsignedInteger('round_index');
            $table->enum('side', ['t', 'ct']);
            $table->enum('pattern_type', [
                'execute',
                'ct_setup',
                'retake',
                'default',
                'utility_lineup',
                'pistol',
                'eco',
            ]);
            $table->string('pattern_subtype')->nullable();
            $table->string('bombsite')->nullable();
            $table->float('confidence')->default(0);
            $table->float('tactical_value_score')->default(0);
            $table->json('evidence')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('start_tick')->nullable();
            $table->unsignedInteger('end_tick')->nullable();
            $table->json('key_ticks')->nullable();
            $table->json('player_steamids')->nullable();
            $table->json('player_names')->nullable();
            $table->string('team_name')->nullable();
            $table->string('opponent_name')->nullable();
            $table->enum('status', [
                'detected',
                'approved',
                'recording',
                'recorded',
                'rejected',
                'released',
            ])->default('detected');
            $table->text('recording_error')->nullable();
            $table->string('video_url')->nullable();
            $table->json('video_renditions')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('tactic_id')->nullable();
            $table->json('pov_durations')->nullable();
            $table->timestamps();

            $table->index(['demo_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detected_tactics');
    }
};
