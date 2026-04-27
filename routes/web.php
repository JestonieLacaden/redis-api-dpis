<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::fallback(function (): JsonResponse {
    return response()->json([
        'message' => 'Not Found',
    ], 404);
});
