<?php

use App\Http\Controllers\CacheController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api-key', 'throttle:cache-api'])->prefix('cache')->group(function () {
    Route::post('/set', [CacheController::class, 'set']);
    Route::get('/get', [CacheController::class, 'get']);
    Route::delete('/delete', [CacheController::class, 'delete']);
});
