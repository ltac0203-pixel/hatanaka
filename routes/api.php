<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CspReportController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('api.auth.register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('api.auth.login');
Route::post('/security/csp-reports', CspReportController::class)->middleware('throttle:30,1')->name('api.security.csp-reports');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/session-status', [AuthController::class, 'sessionStatus'])->name('api.auth.session-status');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('/user', [AuthController::class, 'user'])->name('api.auth.user');

    Route::get('/subscription', [SubscriptionController::class, 'show'])->middleware('ability:subscription:read')->name('api.subscription.show');
    Route::post('/subscription', [SubscriptionController::class, 'store'])->middleware(['throttle:3,1', 'ability:subscription:write'])->name('api.subscription.store');
    Route::delete('/subscription', [SubscriptionController::class, 'destroy'])->middleware(['throttle:5,1', 'ability:subscription:write'])->name('api.subscription.destroy');
    Route::get('/subscription/history', [SubscriptionController::class, 'history'])->middleware('ability:subscription:read')->name('api.subscription.history');

    Route::get('/subscription/plans', [PlanController::class, 'index'])->middleware('ability:subscription:read')->name('api.plans.index');

    Route::get('/subscription/cards', [CardController::class, 'index'])->middleware('ability:card:read')->name('api.cards.index');
    Route::post('/subscription/cards', [CardController::class, 'store'])->middleware(['throttle:3,1', 'ability:card:write'])->name('api.cards.store');
    Route::delete('/subscription/cards/{card}', [CardController::class, 'destroy'])->middleware(['throttle:5,1', 'ability:card:write'])->name('api.cards.destroy');
});
