<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->timestamp('last_active_at')->nullable()->index());
        Schema::create('user_oauth_states', function (Blueprint $table): void {
            $table->string('state_hash', 64)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('cache_key');
            $table->timestamp('expires_at');
        });
        // Start the inactivity window at upgrade, never delete existing accounts immediately.
        DB::table('users')->update(['last_active_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_oauth_states');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('last_active_at'));
    }
};
