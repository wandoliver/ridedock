<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('ai_activity_quota')->nullable();
            $table->unsignedInteger('ai_plan_quota')->nullable();
        });
        Schema::create('operation_mutexes', function (Blueprint $table): void {
            $table->string('name')->primary();
        });
        DB::table('operation_mutexes')->insert(['name' => 'ai']);
        Schema::create('ai_spend_months', function (Blueprint $table): void {
            $table->string('month', 7)->primary();
            $table->unsignedBigInteger('cost_microusd')->default(0);
            $table->unsignedInteger('unknown_costs')->default(0);
        });
        Schema::create('ai_usage_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('analysis_id')->nullable()->constrained('ai_analyses')->nullOnDelete();
            $table->string('feature', 32);
            $table->timestamp('created_at')->index();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('ai_usage_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('analysis_id')->nullable()->constrained('ai_analyses')->nullOnDelete();
            $table->string('feature', 32);
            $table->string('prompt_version', 32);
            $table->string('provider', 32);
            $table->string('model')->nullable();
            $table->string('status', 32);
            $table->boolean('response_received')->default(false);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('cached_input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('cost_microusd')->nullable();
            $table->json('pricing')->nullable();
            $table->timestamp('created_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
        Schema::create('operation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 32);
            $table->string('code', 64);
            $table->string('provider', 32)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamp('created_at')->index();
        });
        Schema::table('provider_connections', function (Blueprint $table): void {
            $table->timestamp('sync_started_at')->nullable();
            $table->timestamp('sync_retry_at')->nullable();
            $table->timestamp('sync_since')->nullable();
        });
        // Existing records retain their original month and continue to consume quota.
        DB::table('ai_analyses')->orderBy('id')->chunk(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('ai_usage_requests')->insert([
                    'user_id' => $row->user_id, 'analysis_id' => $row->id,
                    'feature' => $row->type, 'created_at' => $row->created_at,
                ]);
                if ($row->input_tokens !== null || $row->output_tokens !== null) {
                    $month = substr($row->created_at, 0, 7);
                    DB::table('ai_spend_months')->insertOrIgnore(['month' => $month]);
                    DB::table('ai_spend_months')->where('month', $month)->increment('unknown_costs');
                    DB::table('ai_usage_attempts')->insert([
                        'user_id' => $row->user_id, 'analysis_id' => $row->id,
                        'feature' => $row->type, 'prompt_version' => $row->prompt_version,
                        'provider' => $row->provider ?? 'unknown', 'model' => $row->model,
                        'status' => 'legacy', 'input_tokens' => $row->input_tokens,
                        'output_tokens' => $row->output_tokens, 'created_at' => $row->created_at,
                        'finished_at' => $row->completed_at,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['ai_activity_quota', 'ai_plan_quota']);
        });
        Schema::table('provider_connections', function (Blueprint $table): void {
            $table->dropColumn(['sync_started_at', 'sync_retry_at', 'sync_since']);
        });
        Schema::dropIfExists('operation_events');
        Schema::dropIfExists('ai_usage_attempts');
        Schema::dropIfExists('ai_usage_requests');
        Schema::dropIfExists('operation_mutexes');
        Schema::dropIfExists('ai_spend_months');
    }
};
