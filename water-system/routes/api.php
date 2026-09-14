<?php

use App\Http\Controllers\Api\WaterMeterReadingController;
use Illuminate\Support\Facades\Route;

Route::post('/water-meters/readings', [WaterMeterReadingController::class, 'store'])
    ->middleware('throttle:120,1')
    ->name('api.water-meters.readings.store');
