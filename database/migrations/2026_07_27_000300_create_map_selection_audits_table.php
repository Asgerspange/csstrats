<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail for map selections. When a team disputes
     * what they picked, this is the record.
     */
    public function up(): void
    {
        Schema::create('map_selection_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('team_id');
            $table->json('map_ids');
            $table->unsignedInteger('selected_by');
            $table->timestamp('selected_at')->useCurrent();
            $table->enum('reason', ['initial', 'change', 'pool_rotation', 'upgrade', 'downgrade']);

            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_selection_audits');
    }
};
