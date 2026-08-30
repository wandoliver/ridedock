<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_streams', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('series_type', 16)->default('time');
            $table->string('resolution', 16)->nullable();
            $table->json('data');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'type']);
        });

        Schema::create('activity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->unsignedInteger('elapsed_seconds')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->json('data');
            $table->timestamps();

            $table->index(['activity_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('activity_streams');
    }
};
