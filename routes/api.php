<?php

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\AiPlanController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\CompareController;
use App\Http\Controllers\Api\DistrictController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\ModelController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\ScenarioController;
use Illuminate\Support\Facades\Route;

Route::get('/city', CityController::class);
Route::get('/actions', [ActionController::class, 'index']);
Route::get('/routes', [RouteController::class, 'index']);
Route::post('/routes/preview', [RouteController::class, 'preview'])->middleware('throttle:60,1,osrm-preview');
Route::post('/routes', [RouteController::class, 'store'])->middleware('throttle:20,1,routes');
Route::get('/model', ModelController::class);
Route::get('/scenarios', [ScenarioController::class, 'index']);
Route::post('/scenarios', [ScenarioController::class, 'store'])->middleware('throttle:60,1,scenarios');
Route::get('/scenarios/{scenario}', [ScenarioController::class, 'show']);
Route::get('/compare', CompareController::class)->middleware('throttle:30,1,compare');
Route::post('/ai/plan', AiPlanController::class)->middleware('throttle:10,1,ai');
Route::post('/login', LoginController::class)->middleware('throttle:5,1,login');

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/actions/{action}', [ActionController::class, 'update']);
    Route::put('/districts/{district}', DistrictController::class);
});
