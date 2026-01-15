<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\User\UserController;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::middleware('auth:web')->group(function () {
        Route::get('/me',     [AuthController::class, 'me']);
        Route::post('/logout',[AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| User Routes (Authenticated users)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:web'])->group(function () {
    Route::get('/users/me', [UserController::class, 'me']);
    Route::put('/users/me', [UserController::class, 'updateMe']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes (ADMIN only)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth:web', 'role:ADMIN'])
    ->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']); // ✅ admin create user
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::put('/users/{id}/roles', [UserController::class, 'syncRoles']); // ✅ admin assign roles
    });

use App\Http\Controllers\Catalog\CategoryController;

// Public
Route::get('/categories', [CategoryController::class, 'publicIndex']);
Route::get('/categories/{id}', [CategoryController::class, 'publicShow']);

// Admin
Route::prefix('admin')
    ->middleware(['auth:web', 'role:ADMIN'])
    ->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    });

use App\Http\Controllers\Catalog\SubCategoryController;

// Public
Route::get('/categories/{categoryId}/sub-categories', [SubCategoryController::class, 'publicByCategory']);
Route::get('/sub-categories/{id}', [SubCategoryController::class, 'publicShow']);

// Admin
Route::prefix('admin')
    ->middleware(['auth:web', 'role:ADMIN'])
    ->group(function () {
        Route::get('/sub-categories', [SubCategoryController::class, 'index']);
        Route::post('/sub-categories', [SubCategoryController::class, 'store']);
        Route::get('/sub-categories/{id}', [SubCategoryController::class, 'show']);
        Route::put('/sub-categories/{id}', [SubCategoryController::class, 'update']);
        Route::delete('/sub-categories/{id}', [SubCategoryController::class, 'destroy']);
    });

use App\Http\Controllers\Catalog\ProductController;

// Public
Route::get('/sub-categories/{subCategoryId}/products', [ProductController::class, 'publicBySubCategory']);
Route::get('/products/{id}', [ProductController::class, 'publicShow']);

// Admin
Route::prefix('admin')
    ->middleware(['auth:web', 'role:ADMIN'])
    ->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    });

use App\Http\Controllers\Catalog\ProductVariantController;

// Public
Route::get('/products/{productId}/variants', [ProductVariantController::class, 'publicByProduct']);
Route::get('/variants/{id}', [ProductVariantController::class, 'publicShow']);

// Admin
Route::prefix('admin')
    ->middleware(['auth:web', 'role:ADMIN'])
    ->group(function () {
        Route::get('/variants', [ProductVariantController::class, 'index']);
        Route::post('/variants', [ProductVariantController::class, 'store']);
        Route::get('/variants/{id}', [ProductVariantController::class, 'show']);
        Route::put('/variants/{id}', [ProductVariantController::class, 'update']);
        Route::delete('/variants/{id}', [ProductVariantController::class, 'destroy']);
    });
use App\Http\Controllers\Kiosk\KioskCartController;

/*
|--------------------------------------------------------------------------
| KIOSK (NO LOGIN) - Cart
|--------------------------------------------------------------------------
*/
Route::prefix('kiosk')->group(function () {
    Route::post('/cart/init', [KioskCartController::class, 'init']);
    Route::get('/cart', [KioskCartController::class, 'show']);
    Route::post('/cart/items', [KioskCartController::class, 'addItem']);
    Route::put('/cart/items/{id}', [KioskCartController::class, 'updateItem']);
    Route::delete('/cart/items/{id}', [KioskCartController::class, 'removeItem']);
    Route::delete('/cart/clear', [KioskCartController::class, 'clear']);
    Route::post('/cart/ping', [KioskCartController::class, 'ping']); // Continue button
});

use App\Http\Controllers\Kiosk\KioskOrderController;
/*
|--------------------------------------------------------------------------
| KIOSK (NO LOGIN) - Checkout/Order
|--------------------------------------------------------------------------
*/
Route::prefix('kiosk')->group(function () {
    Route::post('/checkout', [KioskOrderController::class, 'checkout']); // Cart -> Order
    Route::get('/orders/{orderNo}', [KioskOrderController::class, 'showByOrderNo']); // receipt screen
});

use App\Http\Controllers\Checkout\OrderController as AdminOrderController;
/*
|--------------------------------------------------------------------------
| ADMIN - Orders Management (login required)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth:web', 'role:ADMIN'])
    ->group(function () {
        Route::get('/orders', [AdminOrderController::class, 'adminIndex']);
        Route::put('/orders/{id}/status', [AdminOrderController::class, 'adminUpdateStatus']);
    });



