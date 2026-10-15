<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AdminLookupController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LoanController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:login');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('user/dashboard', [DashboardController::class, 'user'])->middleware('role:user');
        Route::middleware('role:user')->prefix('user')->group(function () {
            Route::get('loan-ledgers', [LoanController::class, 'userLedgers']);
            Route::post('loan-ledgers/{ledger}/payment', [LoanController::class, 'recordUserPayment']);
        });
        Route::get('user/profile', [ProfileController::class, 'show'])->middleware('role:user');
        Route::put('user/profile', [ProfileController::class, 'update'])->middleware('role:user');
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'admin']);
            Route::apiResource('users', UserController::class);
            Route::patch('users/{user}/status', [UserController::class, 'status']);
            Route::get('catalog/{kind}', [AdminLookupController::class, 'index']);
            Route::post('catalog/{kind}', [AdminLookupController::class, 'store']);
            Route::put('catalog/{kind}/{record}', [AdminLookupController::class, 'update']);
            Route::delete('catalog/{kind}/{record}', [AdminLookupController::class, 'destroy']);
            Route::post('loan-ledgers/{ledger}/payment', [LoanController::class, 'recordPayment']);
            Route::get('loan-ledgers/{ledger}/payment-reference', [LoanController::class, 'paymentReference']);
            Route::apiResource('loans', LoanController::class);
        });
    });
});
