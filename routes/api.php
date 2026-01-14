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
