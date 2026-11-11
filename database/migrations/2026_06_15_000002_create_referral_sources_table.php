<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('referrers')->cascadeOnDelete();
            // The attribution key. Matched against ?utm_source=<code> and the
            // short link /r/<code>. Lower-cased, unique across all referrers.
            $table->string('code')->unique();
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index('referrer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_sources');
    }
};
