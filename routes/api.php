<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationMonitoringController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api')->group(function (): void {
    Route::post('notifications', [NotificationController::class, 'store'])
        // ->middleware('throttle:60,1')
        ->name('api.v1.notifications.store');

    Route::get('notifications/recent', [NotificationMonitoringController::class, 'recent'])
        ->name('api.v1.notifications.recent');
    Route::get('notifications/summary', [NotificationMonitoringController::class, 'summary'])
        ->name('api.v1.notifications.summary');
});

