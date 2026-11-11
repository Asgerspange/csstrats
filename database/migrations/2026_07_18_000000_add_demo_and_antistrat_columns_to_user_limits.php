<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends user_limits to carry the demo-upload cap and the AI antistrat
     * flag, so PlanService can resolve every tier-defining limit from one row.
     * Defaults match the Starter tier (the most restrictive), so a row that
     * predates this migration reads as Starter until a webhook or admin
     * action writes the real tier's values.
     */
    public function up(): void
    {
        Schema::table('user_limits', function (Blueprint $table) {
            $table->integer('max_demos')->default(2)->after('max_stratbooks');
            $table->boolean('ai_antistrat')->default(false)->after('max_demos');
        });
    }

    public function down(): void
    {
        Schema::table('user_limits', function (Blueprint $table) {
            $table->dropColumn(['max_demos', 'ai_antistrat']);
        });
    }
};
