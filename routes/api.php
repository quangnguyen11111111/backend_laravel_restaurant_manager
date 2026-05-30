<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SocketController;
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
| Category Routes
|--------------------------------------------------------------------------
| Prefix: /categories (user) và /admin/categories (admin)
*/
// User routes - Public
Route::prefix('categories')->group(function () {
    // Lấy danh sách danh mục dạng cây (không phân trang)
    Route::get('/', [CategoryController::class, 'indexForUser']);
    // Lấy chi tiết danh mục
    Route::get('/{id}', [CategoryController::class, 'show'])->whereNumber('id');
});

// Admin routes - Yêu cầu quyền Owner
Route::prefix('admin/categories')->middleware(['jwt.auth', 'role:Owner'])->group(function () {
    // Lấy danh sách danh mục dạng phẳng (có phân trang)
    Route::get('/', [CategoryController::class, 'indexForAdmin']);
    // Tạo danh mục
    Route::post('/', [CategoryController::class, 'store']);
    // Cập nhật danh mục
    Route::put('/{id}', [CategoryController::class, 'update'])->whereNumber('id');
    // Xóa danh mục
    Route::delete('/{id}', [CategoryController::class, 'destroy'])->whereNumber('id');
});

/*
|--------------------------------------------------------------------------
| Dish Routes
|--------------------------------------------------------------------------
| User: GET /dishes (theo category)
| Admin: GET/POST/PUT/DELETE /admin/dishes
*/
// User routes - Public
Route::prefix('dishes')->group(function () {
    // Lấy danh sách dishes theo category (yêu cầu category_id)
    Route::get('/', [DishController::class, 'indexForUser']);
    // Lấy chi tiết dish
    Route::get('/{id}', [DishController::class, 'show'])->whereNumber('id');
});

// Admin routes - Yêu cầu quyền Owner
Route::prefix('admin/dishes')->middleware(['jwt.auth', 'role:Owner'])->group(function () {
    // Lấy danh sách tất cả dishes (admin)
    Route::get('/', [DishController::class, 'indexForAdmin']);
    // Upload ảnh
    Route::post('/image', [DishController::class, 'uploadImage']);
    Route::delete('/image', [DishController::class, 'deleteUploadedImage']);
    // Tạo dish
    Route::post('/', [DishController::class, 'store']);
    // Cập nhật dish
    Route::put('/{id}', [DishController::class, 'update'])->whereNumber('id');
    // Xóa dish
    Route::delete('/{id}', [DishController::class, 'destroy'])->whereNumber('id');
});

/*
|--------------------------------------------------------------------------
| Table Routes
|--------------------------------------------------------------------------
| Prefix: /tables
| Giữ nguyên đường dẫn từ Node.js + giữ nguyên logic CRUD
*/
Route::prefix('tables')->group(function () {
    // Public routes
    Route::get('/', [TableController::class, 'index']);
    Route::get('/{number}', [TableController::class, 'show'])->whereNumber('number');

    // Login AND (Owner OR Employee)
    Route::middleware(['jwt.auth', 'role:Owner'])->group(function () {
        Route::post('/', [TableController::class, 'store']);
        Route::put('/{number}', [TableController::class, 'update'])->whereNumber('number');
        Route::delete('/{number}', [TableController::class, 'destroy'])->whereNumber('number');
    });
});

/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
| Prefix: /guests (giữ nguyên đường dẫn từ Node.js)
*/
Route::prefix('guests')->group(function () {
    Route::post('/auth/login', [GuestController::class, 'login']);
    Route::post('/auth/logout', [GuestController::class, 'logout'])->middleware('jwt.auth');
    Route::post('/auth/refresh-token', [GuestController::class, 'refreshToken']);

    Route::middleware(['jwt.auth', 'role:Guest,Owner,Employee',])->group(function () {
        Route::post('/orders', [GuestController::class, 'createOrders']);
        Route::get('/orders', [GuestController::class, 'getOrders']);
    });
});

/*
|--------------------------------------------------------------------------
| Order Routes
|--------------------------------------------------------------------------
| Prefix: /orders (giữ nguyên đường dẫn từ Node.js)
*/
Route::prefix('orders')->middleware(['jwt.auth', 'role:Owner,Employee'])->group(function () {
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/{orderId}', [OrderController::class, 'show'])->whereNumber('orderId');
    Route::put('/{orderId}', [OrderController::class, 'update'])->whereNumber('orderId');
    Route::post('/pay', [OrderController::class, 'pay']);
});

/*
|--------------------------------------------------------------------------
| Socket Routes (cho Socket Server Node.js)
|--------------------------------------------------------------------------
| Prefix: /sockets
| Các endpoint này được gọi từ socket-server.js để quản lý socket connections
*/
Route::prefix('sockets')->middleware('jwt.auth')->group(function () {
    // Upsert socket connection (được gọi từ socket-server.js)
    Route::post('/upsert', [SocketController::class, 'upsert']);

    // Remove socket connection (được gọi từ socket-server.js)
    Route::post('/remove', [SocketController::class, 'remove']);

    // Find socket ID của user (được gọi từ socket-server.js)
    Route::get('/find/{userId}', [SocketController::class, 'find']);

    // Get all manager socket IDs (để broadcast tới managers)
    Route::get('/managers', [SocketController::class, 'getManagers']);
});
