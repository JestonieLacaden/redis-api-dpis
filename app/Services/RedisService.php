<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RedisService
{
    public function get(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (\Exception $e) {
            Log::error('Redis GET error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            if ($ttl) {
                return Cache::put($key, $value, $ttl);
            }
            return Cache::forever($key, $value);
        } catch (\Exception $e) {
            Log::error('Redis SET error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::error('Redis GET error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}