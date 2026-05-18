<?php

use App\Http\Controllers\MonitorController;
use Illuminate\Support\Facades\Route;

/*
| API Routes
*/

Route::prefix('monitors')->group(function () {
    Route::post('/', [MonitorController::class, 'store']);

    Route::get('/', [MonitorController::class, 'index']);
    Route::get('/{id}/history', [MonitorController::class, 'history'])
        ->where('id', '[0-9]+');
});
