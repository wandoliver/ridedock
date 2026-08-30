<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_connection_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->json('raw_payload')->nullable();
            $table->string('source_file_path')->nullable();
            $table->string('source_file_checksum', 64)->nullable();
            $table->timestamp('external_updated_at')->nullable();
            $table->timestamp('imported_at');
            $table->timestamp('deleted_at_provider')->nullable();
            $table->timestamps();

            $table->unique(['provider_connection_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_sources');
    }
};
