<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Api\TracerouteController;

Route::get('/', function () {
    return Inertia::render('MainPage', [
        'title' => 'ThreeScan',
        ]);
});

// API Routes
Route::prefix('api')->group(function () {
    Route::post('/traceroute', [TracerouteController::class, 'trace']);
});
