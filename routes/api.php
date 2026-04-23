<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DishController;
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
    Route::post('/logout', [AuthController::class, 'logout']);
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
    Route::post('/me/avatar', [AccountController::class, 'uploadAvatar']);
    Route::delete('/me/avatar', [AccountController::class, 'deleteUploadedAvatar']);
    Route::put('/change-password', [AccountController::class, 'changePassword']);

    // Routes cho Owner hoặc Employee
    Route::middleware('role:Owner,Employee')->group(function () {
        Route::post('/guests', [AccountController::class, 'createGuest']);
        Route::get('/guests', [AccountController::class, 'getGuests']);
    });
});

/*
|--------------------------------------------------------------------------
| Dish Routes
|--------------------------------------------------------------------------
| Prefix: /dishes
| Giữ nguyên đường dẫn từ Node.js + giữ nguyên logic CRUD
*/
Route::prefix('dishes')->group(function () {
    // Public routes
    // truyền page để phân trang, mặc định page=1 nếu không truyền
    Route::get('/', [DishController::class, 'index']);
    // cần truyền id
    Route::get('/{id}', [DishController::class, 'show'])->whereNumber('id');

    // Login AND (Owner)
    Route::middleware(['jwt.auth', 'role:Owner'])->group(function () {
        Route::post('/image', [DishController::class, 'uploadImage']);
        Route::delete('/image', [DishController::class, 'deleteUploadedImage']);
        Route::post('/', [DishController::class, 'store']);
        Route::put('/{id}', [DishController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [DishController::class, 'destroy'])->whereNumber('id');
    });
});
