<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table): void {
            $table->foreignUuid('activity_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ai_analyses')->whereNull('activity_id')->delete();

        Schema::table('ai_analyses', function (Blueprint $table): void {
            $table->foreignUuid('activity_id')->nullable(false)->change();
        });
    }
};
