<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opponent_teams', function (Blueprint $table) {
            $table->enum('insights_status', ['pending', 'complete', 'failed'])
                  ->nullable()
                  ->after('insights_updated_at');
            $table->text('insights_error')->nullable()->after('insights_status');
        });
    }

    public function down(): void
    {
        Schema::table('opponent_teams', function (Blueprint $table) {
            $table->dropColumn(['insights_status', 'insights_error']);
        });
    }
};