<?php

namespace App\Http\Controllers;

use App\Services\RedisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CacheController extends Controller
{
    protected RedisService $redisService;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    public function set(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|max:255|regex:/^[A-Za-z0-9_\-\.]+$/',
            'value' => 'required',
            'ttl' => 'nullable|integer|min:1|max:86400',
        ]);

        $success = $this->redisService->set(
            $request->input('key'),
            $request->input('value'),
            $request->input('ttl')
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Cache stored' : 'Failed to store cache',
            'data' => null,
        ]);
    }

    public function get(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|max:255|regex:/^[A-Za-z0-9_\-\.]+$/',
        ]);

        $value = $this->redisService->get($request->query('key'));

        return response()->json([
            'success' => true,
            'message' => $value !== null ? 'Cache retrieved' : 'Cache key not found',
            'data' => $value,
        ]);
    }

    public function delete(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|max:255|regex:/^[A-Za-z0-9_\-\.]+$/',
        ]);

        $success = $this->redisService->delete($request->query('key'));

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Cache deleted' : 'Failed to delete cache',
            'data' => null,
        ]);
    }
}