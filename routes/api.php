<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AnalysisExportController;
use App\Http\Controllers\Api\ProviderConnectionController;
use App\Http\Controllers\OAuth\ProviderOAuthController;
use App\Http\Controllers\Webhooks\HammerheadWebhookController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:120,1')->group(function (): void {
        Route::post('/webhooks/hammerhead', HammerheadWebhookController::class);
    });

    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureActiveAccount::class, 'throttle:60,1'])->group(function (): void {
        Route::middleware(CheckAbilities::class.':activities:read')->group(function (): void {
            Route::get('/activities', [ActivityController::class, 'index']);
            Route::get('/activities/{activity}', [ActivityController::class, 'show']);
            Route::get('/analysis/export', AnalysisExportController::class);
        });

        Route::patch('/activities/{activity}', [ActivityController::class, 'update'])
            ->middleware(CheckAbilities::class.':activities:write');

        Route::middleware(CheckAbilities::class.':connections:write')->group(function (): void {
            Route::get('/connections', [ProviderConnectionController::class, 'index']);
            Route::delete('/connections/{provider}', [ProviderConnectionController::class, 'destroy']);
            Route::post('/connections/{provider}/sync', [ProviderConnectionController::class, 'sync']);
            Route::get('/connections/{provider}/connect', [ProviderOAuthController::class, 'connect']);
        });
    });
});
