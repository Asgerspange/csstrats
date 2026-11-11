<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('invite_token', 32)->nullable()->unique()->after('settings');
        });

        // Backfill existing teams with tokens
        DB::table('teams')->whereNull('invite_token')->get()->each(function ($team) {
            DB::table('teams')
                ->where('id', $team->id)
                ->update(['invite_token' => Str::random(32)]);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('invite_token');
        });
    }
};