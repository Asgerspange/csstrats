<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores per-user limits driven by their active Paddle subscription.
     * Mirrors the fields on GrantedAccess and Product so the same
     * lookup chain works for both Stripe and Paddle users.
     *
     * Updated on: subscription.created, subscription.updated
     * Deleted on: subscription.canceled
     */
    public function up(): void
    {
        Schema::create('user_limits', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unique(); // plain int to match users.id
            $table->integer('max_members')->default(8);
            $table->integer('max_teams')->default(1);
            $table->integer('max_stratbooks')->default(8);
            $table->string('paddle_subscription_id')->nullable(); // for reference
            $table->string('paddle_price_id')->nullable();        // which plan
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_limits');
    }
};