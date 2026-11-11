<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            // Percent of each qualifying order total (after discounts) the
            // referrer earns. e.g. 20.00 = 20%.
            $table->decimal('commission_rate', 5, 2)->default(0);
            // 'first'      => commission only on a customer's first paid order.
            // 'recurring'  => commission on every paid order (optionally capped).
            $table->string('commission_type')->default('first');
            // When commission_type = recurring, optional cap in months from the
            // customer's signup. null = no cap (lifetime).
            $table->unsignedInteger('recurring_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrers');
    }
};
