<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('referrers')->cascadeOnDelete();
            $table->foreignId('referral_source_id')->nullable()
                ->constrained('referral_sources')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Paddle transaction id, unique so a replayed webhook never
            // double-credits a referrer.
            $table->string('paddle_transaction_id')->unique();
            // Order total Paddle charged, after discounts, in major units.
            $table->decimal('order_total', 12, 2)->default(0);
            $table->char('currency', 3)->default('EUR');
            // Rate snapshotted at the time of the order (the referrer's rate
            // may change later).
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->timestamp('billed_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'billed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};
