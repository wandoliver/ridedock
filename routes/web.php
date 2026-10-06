<?php

use App\Http\Controllers\AiAnalysisStatusController;
use App\Http\Controllers\ActivityAiAnalysisController;
use App\Http\Controllers\ActivityDetailController;
use App\Http\Controllers\ActivityComparisonController;
use App\Http\Controllers\ActivityFeedbackController;
use App\Http\Controllers\ActivityIndexController;
use App\Http\Controllers\Api\ProviderConnectionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\ProtectAccountLinks;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoalTrainingPlanController;
use App\Http\Controllers\OAuth\ProviderOAuthController;
use App\Http\Controllers\PlanDayController;
use App\Http\Controllers\Settings\ConnectionSettingsController;
use App\Http\Controllers\Settings\TrainingSettingsController;
use App\Http\Controllers\Settings\TrainingZoneProfileController;
use App\Http\Controllers\TrainingBlockController;
use App\Http\Controllers\WeeklyTrainingPlanController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/activities/{activity}', ActivityDetailController::class)->name('activities.show');
Route::get('/api/v1/oauth/{provider}/callback', [ProviderOAuthController::class, 'callback'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('provider.oauth.callback');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::middleware([ProtectAccountLinks::class, 'guest', 'throttle:20,1'])->group(function (): void {
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');
});

Route::prefix('admin')->name('admin.')->middleware([ProtectAccountLinks::class, 'auth', 'can:manage-users', 'throttle:30,1'])->group(function (): void {
    Route::get('/operations', \App\Http\Controllers\Admin\OperationsController::class)->name('operations');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/ai-quotas', [UserController::class, 'quotas'])->name('users.quotas');
    Route::post('/users/{user}/invitation', [UserController::class, 'reissue'])->name('users.invitation.reissue');
    Route::delete('/users/{user}/invitation', [UserController::class, 'revoke'])->name('users.invitation.revoke');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/settings/ai-usage', \App\Http\Controllers\Settings\AiUsageController::class)->middleware(ProtectAccountLinks::class)->name('settings.ai-usage');
    Route::get('/insights', DashboardController::class)->name('analytics');
    Route::get('/ai-analyses/{analysis}/status', AiAnalysisStatusController::class)
        ->middleware('throttle:120,1')->name('ai-analyses.status');
    Route::get('/coach', CoachController::class)->name('coach.index');
    Route::patch('/coach/plans/{plan}/days/{date}', PlanDayController::class)
        ->middleware('throttle:30,1')->name('coach.days.update');
    Route::get('/activities', ActivityIndexController::class)->name('activities.index');
    Route::post('/activities/{activity}/fit-analysis', \App\Http\Controllers\ReprocessActivityFitController::class)
        ->middleware('throttle:5,1')->name('activities.fit.reprocess');
    Route::patch('/activities/{activity}/comparison', ActivityComparisonController::class)
        ->middleware('throttle:30,1')->name('activities.comparison.update');
    Route::patch('/activities/{activity}/feedback', ActivityFeedbackController::class)
        ->middleware('throttle:30,1')
        ->name('activities.feedback.update');
    Route::get('/settings/connections', ConnectionSettingsController::class)->name('settings.connections');
    Route::post('/settings/privacy/export', [\App\Http\Controllers\Settings\AccountDataController::class, 'export'])->middleware([ProtectAccountLinks::class, 'throttle:3,1'])->name('settings.privacy.export');
    Route::delete('/settings/privacy/account', [\App\Http\Controllers\Settings\AccountDataController::class, 'destroy'])->middleware([ProtectAccountLinks::class, 'throttle:5,1'])->name('settings.privacy.account.destroy');
    Route::get('/settings/privacy', [\App\Http\Controllers\Settings\PrivacySettingsController::class, 'show'])->name('settings.privacy');
    Route::patch('/settings/privacy', [\App\Http\Controllers\Settings\PrivacySettingsController::class, 'update'])->name('settings.privacy.update');
    Route::post('/settings/privacy/zones', [\App\Http\Controllers\Settings\PrivacySettingsController::class, 'storeZone'])->middleware('throttle:20,1')->name('settings.privacy.zones.store');
    Route::delete('/settings/privacy/zones/{zone}', [\App\Http\Controllers\Settings\PrivacySettingsController::class, 'destroyZone'])->name('settings.privacy.zones.destroy');
    Route::post('/settings/privacy/cleanup', [\App\Http\Controllers\Settings\PrivacySettingsController::class, 'purge'])->middleware('throttle:5,1')->name('settings.privacy.cleanup');
    Route::get('/settings/training', [TrainingSettingsController::class, 'show'])->name('settings.training');
    Route::post('/settings/training/zones', TrainingZoneProfileController::class)
        ->middleware('throttle:20,1')->name('settings.training.zones.store');
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
    Route::delete('/connections/{provider}', [ProviderConnectionController::class, 'destroy'])
        ->middleware('throttle:20,1')->name('provider.disconnect');
    Route::post('/connections/{provider}/sync', [ProviderConnectionController::class, 'sync'])
        ->middleware('throttle:20,1')
        ->name('provider.sync');
});

if (app()->isLocal() && file_exists(base_path('routes/local.php'))) {
    require base_path('routes/local.php');
}
