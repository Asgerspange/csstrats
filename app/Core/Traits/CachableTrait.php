<?php

namespace App\Core\Traits;

use Illuminate\Support\Facades\Cache;

trait CachableTrait
{
     /**
     * Get a cached value by key or execute a callback and cache the result.
     *
     * @param string $key The cache key.
     * @param int $ttl Time to live (in seconds) for the cache.
     * @param callable $callback Callback to get data if cache is missing.
     * @return mixed
     */
    public static function rememberCache(string $key, int $ttl, callable $callback)
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Retrieve a value from the cache by key.
     *
     * @param string $key The cache key.
     * @return mixed
     */
    public static function getCache(string $key)
    {
        return Cache::get($key);
    }

    /**
     * Put an item in the cache for a specified time.
     *
     * @param string $key The cache key.
     * @param mixed $value The value to cache.
     * @param int $ttl Time to live (in seconds) for the cache.
     * @return void
     */
    public static function putCache(string $key, $value, int $ttl)
    {
        Cache::put($key, $value, $ttl);
    }

    /**
     * Check if a cache key exists.
     *
     * @param string $key The cache key.
     * @return bool
     */
    public static function hasCache(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * Delete a cache entry by key.
     *
     * @param string $key The cache key.
     * @return bool
     */
    public static function forgetCache(string $key): bool
    {
        return Cache::forget($key);
    }
}
