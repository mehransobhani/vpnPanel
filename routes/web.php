<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| عمومی
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');

// لینک اشتراک — همان چیزی که در کلاینت وارد می‌شود. بدون لاگین.
Route::get('/sub/{token}', SubscriptionLinkController::class)
    ->name('sub')
    ->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| احراز هویت
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| پنل کاربر
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}/pay', [OrderController::class, 'pay'])->name('orders.pay');
    Route::post('/orders/{order}/receipt', [OrderController::class, 'submitReceipt'])->name('orders.receipt');

    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
    Route::get('/subscriptions/{subscription}/qr', [SubscriptionController::class, 'qr'])->name('subscriptions.qr');
    Route::get('/subscriptions/{subscription}/download', [SubscriptionController::class, 'download'])->name('subscriptions.download');
});

/*
|--------------------------------------------------------------------------
| پنل مدیریت
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::resource('servers', Admin\ServerController::class)->except('show');
        Route::post('servers/{server}/test', [Admin\ServerController::class, 'test'])->name('servers.test');
        Route::post('servers/{server}/resync', [Admin\ServerController::class, 'resync'])->name('servers.resync');
        Route::post('servers/{server}/inbounds', [Admin\InboundController::class, 'store'])->name('inbounds.store');
        Route::put('inbounds/{inbound}', [Admin\InboundController::class, 'update'])->name('inbounds.update');
        Route::delete('inbounds/{inbound}', [Admin\InboundController::class, 'destroy'])->name('inbounds.destroy');

        Route::resource('plans', Admin\PlanController::class)->except('show');

        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::post('orders/{order}/approve', [Admin\OrderController::class, 'approve'])->name('orders.approve');
        Route::post('orders/{order}/reject', [Admin\OrderController::class, 'reject'])->name('orders.reject');

        Route::get('subscriptions', [Admin\SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions', [Admin\SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::get('subscriptions/{subscription}', [Admin\SubscriptionController::class, 'show'])->name('subscriptions.show');
        Route::put('subscriptions/{subscription}', [Admin\SubscriptionController::class, 'update'])->name('subscriptions.update');
        Route::post('subscriptions/{subscription}/action', [Admin\SubscriptionController::class, 'action'])->name('subscriptions.action');
        Route::delete('subscriptions/{subscription}', [Admin\SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');

        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        Route::put('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    });
