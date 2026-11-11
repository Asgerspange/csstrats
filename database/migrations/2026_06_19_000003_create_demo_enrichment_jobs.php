<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Queue of "stamp callouts onto these rounds' stored frame_data" jobs,
     * polled by the demoparser service. Used when promoting an already-parsed
     * demo to an admin demo (the .dem chunks are gone, so we enrich the stored
     * gzipped frame_data in place). Status reuses App\Enums\QueuedDemoStatus
     * string values (pending|processing|failed|completed).
     */
    public function up(): void
    {
        if (Schema::hasTable('demo_enrichment_jobs')) {
            return;
        }

        Schema::create('demo_enrichment_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('demo_id')->index();
            $table->json('round_indices');
            $table->string('status')->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_enrichment_jobs');
    }
};
