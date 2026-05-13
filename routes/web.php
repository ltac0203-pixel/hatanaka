<?php

declare(strict_types=1);

use App\Http\Controllers\CardController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    return $request->user()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

// メール検証済みのユーザーだけが本サービスのコア機能 (契約・カード・ダッシュボード) を
// 利用できるように 'verified' ミドルウェアで保護する。/verify-email や /profile は
// 認証だけで通す (routes/auth.php 側の 'auth' グループ)。
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // 契約候補を比較して選べるよう、プラン参照系の導線をまとめる。
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/{fincode_plan_id}', [PlanController::class, 'show'])
        ->name('plans.show')
        ->where('fincode_plan_id', '[A-Za-z0-9_-]+');

    // 課金状態を自己管理できるよう、契約の参照・登録・解約を保護下へ置く。
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscription', [SubscriptionController::class, 'store'])->middleware('throttle:3,1')->name('subscription.store');
    Route::delete('/subscription/{subscription}', [SubscriptionController::class, 'destroy'])->middleware('throttle:5,1')->name('subscription.destroy');

    // 決済手段を安全に追加・削除できるようカード操作も認証下へ限定する。
    Route::get('/cards/create', [CardController::class, 'create'])->name('cards.create');
    Route::post('/cards', [CardController::class, 'store'])->middleware('throttle:3,1')->name('cards.store');
    Route::delete('/cards/{card}', [CardController::class, 'destroy'])->middleware('throttle:5,1')->name('cards.destroy');
});

require __DIR__.'/auth.php';
