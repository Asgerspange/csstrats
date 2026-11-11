<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_spatial', function (Blueprint $table) {
            $table->id();
            $table->integer('demo_id')->index();
            $table->enum('type', ['paths', 'utility', 'shots', 'plants', 'entries']);
            $table->longText('data'); // gzip compressed JSON stored as base64
            $table->timestamps();

            $table->unique(['demo_id', 'type']);
            $table->foreign('demo_id')->references('id')->on('demos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_spatial');
    }
};