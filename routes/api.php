<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Public group: readable without authentication (health, later curriculum/lessons).
| Auth group: requires Sanctum token (Phase 2+). Favorites, progress, quizzes,
| coaching, and other privileged actions belong here — never on public routes.
|
*/

Route::middleware('throttle:api')->group(function (): void {
    // Public routes — no authentication required
    Route::get('/health', HealthController::class)->name('health');

    // Future public curriculum read endpoints (Phase 4):
    // Route::get('/curriculum', ...);
    // Route::get('/levels', ...);
    // Route::get('/lessons', ...);
    // Route::get('/lessons/{lesson}', ...);
});

/*
| Authenticated routes — Sanctum middleware added in Phase 2.
| Placeholder keeps the public vs auth boundary explicit from day one.
|
| Route::middleware('auth:sanctum')->group(function (): void {
|     // favorites, progress, quizzes, goals, sessions, messages, ...
| });
*/
