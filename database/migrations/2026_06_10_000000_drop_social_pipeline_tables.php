<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the social pipeline tables. The n8n marketing automation and the
 * social-attribution feature that owned these tables have been removed.
 *
 * This migration is intentionally one-way: the dropped data is not restored
 * on rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('social_events');
        Schema::dropIfExists('social_posts');
    }

    public function down(): void
    {
        // No-op. The social pipeline has been retired and its data is not
        // recoverable; rolling back does not recreate these tables.
    }
};
