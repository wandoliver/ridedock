<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('deduplication_key', 64);
            $table->string('name');
            $table->string('type', 32)->default('ride');
            $table->timestamp('started_at');
            $table->string('timezone')->nullable();
            $table->decimal('distance_m', 12, 3)->nullable();
            $table->unsignedInteger('moving_time_s')->nullable();
            $table->unsignedInteger('elapsed_time_s')->nullable();
            $table->decimal('elevation_gain_m', 10, 2)->nullable();
            $table->decimal('average_speed_mps', 8, 3)->nullable();
            $table->decimal('max_speed_mps', 8, 3)->nullable();
            $table->unsignedSmallInteger('average_heart_rate')->nullable();
            $table->unsignedSmallInteger('max_heart_rate')->nullable();
            $table->decimal('average_cadence', 7, 2)->nullable();
            $table->decimal('max_cadence', 7, 2)->nullable();
            $table->decimal('average_power', 8, 2)->nullable();
            $table->decimal('weighted_average_power', 8, 2)->nullable();
            $table->decimal('max_power', 8, 2)->nullable();
            $table->decimal('energy_kj', 10, 2)->nullable();
            $table->unsignedTinyInteger('rpe')->nullable();
            $table->text('notes')->nullable();
            $table->text('summary_polyline')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'deduplication_key']);
            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
