<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PendingPaymentController;
use App\Http\Controllers\PublicPaymentMethodController;
use App\Http\Controllers\QrisPaymentController;
use App\Http\Controllers\RevenueReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class);

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::get('/track/{invoiceNumber}', [OrderController::class, 'track'])->name('orders.track');
Route::post('/track/{invoiceNumber}/qris', [QrisPaymentController::class, 'publicGenerate'])
    ->middleware('throttle:6,1')
    ->name('orders.track.qris.generate');
Route::patch('/track/{invoiceNumber}/qris/{payment}/check', [QrisPaymentController::class, 'publicCheck'])
    ->middleware('throttle:12,1')
    ->name('orders.track.qris.check');
Route::post('/track/{invoiceNumber}/payment-method', [PublicPaymentMethodController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('orders.track.payment-method.store');
Route::post('/track/{invoiceNumber}/payment-method/{payment}/proof', [PublicPaymentMethodController::class, 'uploadProof'])
    ->middleware('throttle:8,1')
    ->name('orders.track.payment-method.proof');
Route::post('/webhooks/autogopay', [QrisPaymentController::class, 'webhook'])->name('webhooks.autogopay');
Route::post('/webhook/gopay', [QrisPaymentController::class, 'webhook'])->name('webhooks.autogopay.legacy');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/schedule', ScheduleController::class)->name('schedule.index');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/payments/pending', PendingPaymentController::class)->name('payments.pending');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');
    Route::patch('/orders/{order}/payment', [OrderController::class, 'updatePayment'])->name('orders.payment.update');
    Route::patch('/orders/{order}/payments/{payment}/verify', [OrderController::class, 'verifyPaymentRequest'])->name('orders.payments.verify');
    Route::post('/orders/{order}/qris', [QrisPaymentController::class, 'generate'])->name('orders.qris.generate');
    Route::patch('/orders/{order}/qris/{payment}/check', [QrisPaymentController::class, 'check'])->name('orders.qris.check');
    Route::delete('/orders/{order}/qris/{payment}', [QrisPaymentController::class, 'cancel'])->name('orders.qris.cancel');
    Route::delete('/orders/{order}/payments/{payment}', [OrderController::class, 'destroyPayment'])->name('orders.payments.destroy');
    Route::post('/orders/{order}/items/{item}/after-photo', [OrderController::class, 'uploadAfterPhoto'])->name('orders.items.after-photo');
    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::middleware('role:admin,kasir')->group(function () {
        Route::resource('services', ServiceController::class)->except(['show']);
        Route::get('/reports/revenue', RevenueReportController::class)->name('reports.revenue');
        Route::get('/reports/revenue/export', [RevenueReportController::class, 'export'])->name('reports.revenue.export');
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
