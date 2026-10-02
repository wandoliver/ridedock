<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_zone_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('effective_at');
            $table->unsignedSmallInteger('ftp_watts')->nullable();
            $table->unsignedSmallInteger('max_heart_rate')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'effective_at']);
        });
        // Existing values are known now; their historical measurement dates are unknown.
        DB::table('athlete_profiles')->where(function ($query): void {
            $query->whereNotNull('ftp_watts')->orWhereNotNull('max_heart_rate');
        })->orderBy('id')->chunkById(200, function ($profiles): void {
            foreach ($profiles as $profile) {
                DB::table('training_zone_profiles')->insert([
                    'user_id' => $profile->user_id, 'effective_at' => now(),
                    'ftp_watts' => $profile->ftp_watts, 'max_heart_rate' => $profile->max_heart_rate,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_zone_profiles');
    }
};
