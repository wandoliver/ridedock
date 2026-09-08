<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_connections', function (Blueprint $table): void {
            $table->text('access_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('provider_connections')->whereNull('access_token')->update(['access_token' => Crypt::encryptString('')]);
        Schema::table('provider_connections', function (Blueprint $table): void {
            $table->text('access_token')->nullable(false)->change();
        });
    }
};
