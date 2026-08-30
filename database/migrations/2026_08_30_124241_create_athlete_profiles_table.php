<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('athlete_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('goal', 500)->nullable();
            $table->date('goal_date')->nullable();
            $table->string('experience_level', 32)->nullable();
            $table->decimal('weekly_hours', 4, 1)->nullable();
            $table->json('available_days')->nullable();
            $table->unsignedSmallInteger('ftp_watts')->nullable();
            $table->unsignedSmallInteger('max_heart_rate')->nullable();
            $table->text('constraints')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('athlete_profiles');
    }
};
