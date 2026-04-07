<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('jwt.auth');

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
| Prefix: /auth
| Các API xác thực (giữ nguyên đường dẫn từ Node.js)
*/
Route::prefix('auth')->group(function () {
    // Public routes (không cần đăng nhập)
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/login/google', [AuthController::class, 'loginGoogle']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

    // Protected routes (cần đăng nhập)
    Route::middleware('jwt.auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Account Routes
|--------------------------------------------------------------------------
| Prefix: /accounts
| Các API quản lý tài khoản (giữ nguyên đường dẫn từ Node.js)
*/
Route::prefix('accounts')->middleware('jwt.auth')->group(function () {
    // Routes yêu cầu quyền Owner
    Route::middleware('role:Owner')->group(function () {
        Route::get('/', [AccountController::class, 'index']);
        Route::post('/', [AccountController::class, 'store']);
        Route::get('/detail/{id}', [AccountController::class, 'show']);
        Route::put('/detail/{id}', [AccountController::class, 'update']);
        Route::delete('/detail/{id}', [AccountController::class, 'destroy']);
    });

    // Routes chỉ cần đăng nhập
    Route::get('/me', [AccountController::class, 'me']);
    Route::put('/me', [AccountController::class, 'updateMe']);
    Route::put('/change-password', [AccountController::class, 'changePassword']);

    // Routes cho Owner hoặc Employee
    Route::middleware('role:Owner,Employee')->group(function () {
        Route::post('/guests', [AccountController::class, 'createGuest']);
        Route::get('/guests', [AccountController::class, 'getGuests']);
    });
});
