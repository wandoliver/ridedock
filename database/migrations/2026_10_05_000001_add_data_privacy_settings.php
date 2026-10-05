<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('activity_retention_days')->nullable();
            $table->unsignedInteger('source_retention_days')->nullable();
        });
        Schema::table('activity_sources', function (Blueprint $table): void {
            $table->timestamp('purged_at')->nullable();
        });
        Schema::create('privacy_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_m');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_zones');
        Schema::table('activity_sources', fn (Blueprint $table) => $table->dropColumn('purged_at'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['activity_retention_days', 'source_retention_days']));
    }
};
