<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('app.api_key');
        $requestKey = (string) $request->header('X-API-KEY', '');

        if (!$request->hasHeader('X-API-KEY')) {
            return response()->json([
                'success' => false,
                'message' => 'API key missing',
                'data' => null
            ], 403);
        }

        if (!$apiKey || !hash_equals($apiKey, $requestKey)) {
            Log::warning('Invalid API Key attempt', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}