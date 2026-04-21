<?php

use App\Http\Controllers\CacheController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfProxyController;

Route::middleware(['api-key', 'throttle:cache-api'])->prefix('cache')->group(function () {
    Route::post('/set', [CacheController::class, 'set']);
    Route::get('/get', [CacheController::class, 'get']);
    Route::delete('/delete', [CacheController::class, 'delete']);
});


Route::middleware(['api-key', 'throttle:pdf-api'])->group(function () {
    Route::post('/forms/libreoffice/convert', [PdfProxyController::class, 'libreofficeConvert']);
    Route::post('/forms/chromium/convert/html', [PdfProxyController::class, 'chromiumConvertHtml']);
    Route::post('/forms/chromium/convert/url', [PdfProxyController::class, 'chromiumConvertUrl']);
});