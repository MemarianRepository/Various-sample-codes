<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoinexMarketInfo extends Model
{
    use HasFactory;

    protected $table = 'coinex_market_info';

    public static function getLastInfo()
    {
        return CoinexMarketInfo::query()->get()->last();
    }
}
