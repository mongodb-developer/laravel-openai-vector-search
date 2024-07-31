<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PointOfInterestController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Get list of supported cities
Route::get('/cities', [PointOfInterestController::class, 'getSupportedCities']);

// Get top points of interest for a specific city
Route::get('/cities/top-points', [PointOfInterestController::class, 'getTopPointsForCity']);

// Search points of interest within a city
Route::get('/cities/search', [PointOfInterestController::class, 'searchByCity']);

// Plan trip

Route::post('/cities/plan-trip', [PointOfInterestController::class, 'planTrip']);