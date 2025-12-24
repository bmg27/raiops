<?php

use App\Http\Controllers\Api\RaiWebhookController;
use App\Http\Controllers\Api\ScheduleCallbackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| RAI Webhook Endpoints
|--------------------------------------------------------------------------
|
| These endpoints receive events from RAI instances for centralized
| logging and monitoring in RAIOPS.
|
*/

Route::prefix('webhooks/rai')->group(function () {
    // Health check - RAI can verify connectivity
    Route::get('/health', [RaiWebhookController::class, 'healthCheck'])
        ->name('api.webhooks.rai.health');

    // Audit event push
    Route::post('/audit', [RaiWebhookController::class, 'handleAuditEvent'])
        ->name('api.webhooks.rai.audit');
});

/*
|--------------------------------------------------------------------------
| Schedule Callback Endpoint
|--------------------------------------------------------------------------
|
| Receives progress callbacks from RAI instances during schedule execution.
| Used by the webhook-based schedule runner system.
|
*/

Route::post('/webhook/schedule-callback', [ScheduleCallbackController::class, 'handle'])
    ->name('api.webhook.schedule-callback');
