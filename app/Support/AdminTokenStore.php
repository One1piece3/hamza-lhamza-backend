<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class AdminTokenStore
{
    public static function repository(): Repository
    {
        return Cache::store(config('auth.admin_token_store', 'file'));
    }

    public static function put(string $token, int $userId, mixed $ttl): bool
    {
        return self::repository()->put(self::cacheKey($token), $userId, $ttl);
    }

    public static function get(string $token): mixed
    {
        return self::repository()->get(self::cacheKey($token));
    }

    public static function forget(string $token): bool
    {
        return self::repository()->forget(self::cacheKey($token));
    }

    public static function cacheKey(string $token): string
    {
        return 'admin_token_' . $token;
    }
}
