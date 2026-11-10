<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfirmationController;
use App\Http\Controllers\OccurrenceController;
use App\Http\Controllers\RouteAnalysisController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for CitizenMap
|--------------------------------------------------------------------------
*/

// Public Authentication
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Public Exploration & Map Data
Route::get('/occurrences', [OccurrenceController::class, 'index']);
Route::get('/occurrences/{occurrence}', [OccurrenceController::class, 'show']);
Route::get('/neighborhoods', [OccurrenceController::class, 'neighborhoods']);
Route::get('/statistics', [OccurrenceController::class, 'statistics']);

// Route Hazard & Spatial Conflict Analysis (publicly accessible so any citizen can route safely)
Route::post('/routes/calculate', [RouteAnalysisController::class, 'calculateAndAnalyze']);
Route::post('/routes/analyze', [RouteAnalysisController::class, 'analyze']);

// Authenticated Routes (Requires Sanctum Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Occurrences Management
    Route::post('/occurrences', [OccurrenceController::class, 'store']);
    Route::match(['put', 'patch'], '/occurrences/{occurrence}', [OccurrenceController::class, 'update']);
    Route::delete('/occurrences/{occurrence}', [OccurrenceController::class, 'destroy']);

    // Community Social Validation ("Likes" / Confirmations)
    Route::post('/occurrences/{occurrence}/confirm', [ConfirmationController::class, 'toggle']);
});
