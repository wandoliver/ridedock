<?php

use App\Http\Controllers\ActivityAiAnalysisController;
use App\Http\Controllers\ActivityDetailController;
use App\Http\Controllers\ActivityFeedbackController;
use App\Http\Controllers\ActivityIndexController;
use App\Http\Controllers\Api\ProviderConnectionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoalTrainingPlanController;
use App\Http\Controllers\OAuth\ProviderOAuthController;
use App\Http\Controllers\PlanDayController;
use App\Http\Controllers\Settings\ConnectionSettingsController;
use App\Http\Controllers\Settings\TrainingSettingsController;
use App\Http\Controllers\TrainingBlockController;
use App\Http\Controllers\WeeklyTrainingPlanController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/activities/{activity}', ActivityDetailController::class)->name('activities.show');
Route::get('/api/v1/oauth/{provider}/callback', [ProviderOAuthController::class, 'callback'])
    ->middleware('throttle:30,1')
    ->name('provider.oauth.callback');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/coach', CoachController::class)->name('coach.index');
    Route::patch('/coach/plans/{plan}/days/{date}', PlanDayController::class)
        ->middleware('throttle:30,1')->name('coach.days.update');
    Route::get('/activities', ActivityIndexController::class)->name('activities.index');
    Route::patch('/activities/{activity}/feedback', ActivityFeedbackController::class)
        ->middleware('throttle:30,1')
        ->name('activities.feedback.update');
    Route::get('/settings/connections', ConnectionSettingsController::class)->name('settings.connections');
    Route::get('/settings/training', [TrainingSettingsController::class, 'show'])->name('settings.training');
    Route::patch('/settings/training', [TrainingSettingsController::class, 'update'])->name('settings.training.update');
    Route::post('/activities/{activity}/ai-analysis', ActivityAiAnalysisController::class)
        ->middleware('throttle:10,1')
        ->name('activities.ai-analysis.store');
    Route::post('/coach/weekly-plan', WeeklyTrainingPlanController::class)
        ->middleware('throttle:5,1')
        ->name('coach.weekly-plan.store');
    Route::post('/coach/goal-plan', GoalTrainingPlanController::class)
        ->middleware('throttle:5,1')
        ->name('coach.goal-plan.store');
    Route::post('/coach/training-block', TrainingBlockController::class)
        ->middleware('throttle:5,1')
        ->name('coach.training-block.store');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/connections/{provider}/connect', [ProviderOAuthController::class, 'connect'])
        ->middleware('throttle:20,1')
        ->name('provider.connect');
    Route::post('/connections/{provider}/sync', [ProviderConnectionController::class, 'sync'])
        ->middleware('throttle:20,1')
        ->name('provider.sync');
});

if (app()->isLocal() && file_exists(base_path('routes/local.php'))) {
    require base_path('routes/local.php');
}
