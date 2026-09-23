<?php

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\ModelController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\ScenarioController;
use Illuminate\Support\Facades\Route;

Route::get('/city', CityController::class);
Route::get('/actions', [ActionController::class, 'index']);
Route::get('/routes', RouteController::class);
Route::get('/model', ModelController::class);
Route::get('/scenarios', [ScenarioController::class, 'index']);
Route::post('/scenarios', [ScenarioController::class, 'store'])->middleware('throttle:60,1');
Route::get('/scenarios/{scenario}', [ScenarioController::class, 'show']);
