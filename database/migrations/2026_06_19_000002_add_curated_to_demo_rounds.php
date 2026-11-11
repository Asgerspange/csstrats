<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a round as curated for admin/tactics use. An admin demo exposes
     * only its curated rounds; admins can add more over time.
     */
    public function up(): void
    {
        Schema::table('demo_rounds', function (Blueprint $table) {
            if (!Schema::hasColumn('demo_rounds', 'curated')) {
                $table->boolean('curated')->default(false)->index()->after('round_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('demo_rounds', function (Blueprint $table) {
            if (Schema::hasColumn('demo_rounds', 'curated')) {
                $table->dropColumn('curated');
            }
        });
    }
};
