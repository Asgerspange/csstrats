<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an opt-in share token to round_plans.
 *
 * When a user enables sharing on a plan, a UUID token is generated and
 * share_enabled is set true. The public route /plan/{token} renders a
 * read-only viewer. Disabling sharing clears the token (or sets
 * share_enabled false), immediately revoking all prior links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('round_plans', function (Blueprint $table) {
            $table->uuid('share_token')->nullable()->unique()->after('snapshot_url');
            $table->boolean('share_enabled')->default(false)->after('share_token');
        });
    }

    public function down(): void
    {
        Schema::table('round_plans', function (Blueprint $table) {
            $table->dropColumn(['share_token', 'share_enabled']);
        });
    }
};
