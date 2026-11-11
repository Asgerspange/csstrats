<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the round_plans table.
 * A RoundPlan is an authored animated round attached to a StratbookTactic
 * (same pattern as Board → StratbookTactic).
 *
 * Run: php artisan migrate
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_plans', function (Blueprint $table) {
            $table->id();

            // Optional link to a stratbook tactic (nullable so a plan can exist standalone)
            $table->integer('stratbook_tactic_id')->nullable()->index();
            $table->foreign('stratbook_tactic_id')
                  ->references('id')
                  ->on('stratbook_tactics')
                  ->nullOnDelete();

            // The team that owns this plan
            $table->integer('team_id')->nullable()->index();
            $table->foreign('team_id')
                  ->references('id')
                  ->on('teams')
                  ->cascadeOnDelete();

            // Creator
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // Plan metadata
            $table->string('name');
            $table->string('map')->nullable();
            $table->string('side', 2)->default('T');  // 'T' | 'CT'

            // Full plan JSON  (players, paths, utilities, instructions)
            $table->longText('data');

            // PNG snapshot for preview cards (stored in S3 / local disk)
            $table->string('snapshot_path')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_plans');
    }
};