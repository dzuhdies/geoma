<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangeController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ValidationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RegionController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [RegionController::class, 'index']);

Route::prefix('api')->group(function () {
    // Auth routes
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Public / Read-only data endpoints
    Route::get('/search', [RegionController::class, 'search']);
    Route::get('/filter-options', [RegionController::class, 'filterOptions']);
    Route::get('/regions', [RegionController::class, 'geojson']);
    Route::get('/regions/{id}', [RegionController::class, 'show']);
    Route::get('/regions/{id}/neighbors', [RegionController::class, 'neighbors']);

    Route::get('/changed-regions', [ChangeController::class, 'index']);
    Route::get('/changed-regions/export', [ChangeController::class, 'exportCsv']);

    Route::get('/validation', [ValidationController::class, 'index']);
    Route::get('/validation/export', [ValidationController::class, 'exportCsv']);

    // Protected mutation endpoints (requires login)
    Route::middleware('auth')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/regions/{id}/change', [ChangeController::class, 'markChanged']);
        Route::delete('/regions/{id}/change', [ChangeController::class, 'unmarkChanged']);
        Route::post('/regions/{id}/relations', [ChangeController::class, 'addRelation']);
        Route::delete('/regions/{id}/relations', [ChangeController::class, 'removeRelation']);
    });
});
