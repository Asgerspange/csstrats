<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration ensures every existing user has onboarding_completed = true
     * (we don't want to force existing users through onboarding).
     * New users will get onboarding_completed = false set in RegisterController.
     */
    public function up(): void
    {
        // Backfill existing users: mark onboarding as already completed
        DB::table('users')
            ->whereNull('settings')
            ->orWhere('settings', '[]')
            ->orWhere('settings', '')
            ->update(['settings' => json_encode(['onboarding_completed' => false])]);

        // For users who already have settings, merge in onboarding_completed = true
        $users = DB::table('users')
            ->whereNotNull('settings')
            ->where('settings', '!=', '[]')
            ->where('settings', '!=', '')
            ->get(['id', 'settings']);

        foreach ($users as $user) {
            $settings = json_decode($user->settings, true);
            if (is_array($settings) && !isset($settings['onboarding_completed'])) {
                $settings['onboarding_completed'] = false;
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['settings' => json_encode($settings)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $users = DB::table('users')->whereNotNull('settings')->get(['id', 'settings']);

        foreach ($users as $user) {
            $settings = json_decode($user->settings, true);
            if (is_array($settings)) {
                unset($settings['onboarding_completed']);
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['settings' => json_encode($settings)]);
            }
        }
    }
};