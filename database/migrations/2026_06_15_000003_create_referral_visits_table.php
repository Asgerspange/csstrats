<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_source_id')->constrained('referral_sources')->cascadeOnDelete();
            // Anonymous first-party visitor id (cs_vid cookie). Lets us count
            // unique visitors and dedupe repeated page views.
            $table->string('visitor_id', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('country_code', 2)->nullable();
            // The off-site page that linked here (HTTP Referer header).
            $table->string('referrer_url', 512)->nullable();
            $table->string('landing_path', 512)->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['referral_source_id', 'created_at']);
            $table->index('visitor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_visits');
    }
};
