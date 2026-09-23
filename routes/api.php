<?php

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\ModelController;
use App\Http\Controllers\Api\RouteController;
use Illuminate\Support\Facades\Route;

Route::get('/city', CityController::class);
Route::get('/actions', [ActionController::class, 'index']);
Route::get('/routes', RouteController::class);
Route::get('/model', ModelController::class);
