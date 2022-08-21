<?php


namespace App\Helpers;


use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    // Create expire time cache for activation code
    public static function createCache($activation_code, $expire_time = 30)
    {
        Cache::put('activation_code_'.$activation_code, true, '30');
    }

    // Check expire time cache exist
    public static function checkCache($activation_code): bool
    {
        $expire_time = Cache::has('activation_code_'.$activation_code);
        if($expire_time)
            return true;
        else
            return false;
    }
}
