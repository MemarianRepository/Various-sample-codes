<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminUsdtPrice extends Model
{
    use HasFactory;

    protected $table = 'admin_usdt_prices';

    public static function get()
    {
        return self::query()->first();
    }
}
