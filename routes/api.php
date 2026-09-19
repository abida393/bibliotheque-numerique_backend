<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContributionRequestController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/resources', [ResourceController::class, 'index']);
    Route::get('/resources/{resource}', [ResourceController::class, 'show']);
    Route::get('/resources/{resource}/download', [ResourceController::class, 'download']);
    Route::post('/resources', [ResourceController::class, 'store']);
    Route::put('/resources/{resource}', [ResourceController::class, 'update']);
    Route::delete('/resources/{resource}', [ResourceController::class, 'destroy']);
    Route::get('/my-contributions', [ContributionRequestController::class, 'myContributions']);
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/resources/{resource}/favorite', [FavoriteController::class, 'store']);
    Route::delete('/resources/{resource}/favorite', [FavoriteController::class, 'destroy']);
    Route::get('/resources/{resource}/reviews', [ReviewController::class, 'index']);
    Route::post('/resources/{resource}/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::get('/my-history', [ResourceController::class, 'myHistory']);
});


// --- Écriture réservée aux modérateurs et admins ---
Route::middleware(['auth:sanctum', 'role:moderator|admin'])->group(function () {
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    Route::get('/contribution-requests', [ContributionRequestController::class, 'index']);
    Route::post('/contribution-requests/{contributionRequest}/validate', [ContributionRequestController::class, 'validate']);
    Route::post('/contribution-requests/{contributionRequest}/reject', [ContributionRequestController::class, 'reject']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'index']);
    Route::put('/admin/users/{user}/role', [AdminController::class, 'updateRole']);
    Route::delete('/admin/users/{user}', [AdminController::class, 'destroy']);

    Route::get('/stats/overview', [StatisticsController::class, 'overview']);
    Route::get('/stats/popular-resources', [StatisticsController::class, 'popularResources']);
    Route::get('/stats/resources-by-category', [StatisticsController::class, 'resourcesByCategory']);
    Route::get('/stats/activity-trend', [StatisticsController::class, 'activityTrend']);
});
