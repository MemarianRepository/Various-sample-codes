<?php

namespace App\Repositories\Api\v1;

use App\Models\Api\v1\ExchangeList;


class ExchangeListRepository
{
    public static function mainCurrencies()
    {
        return ExchangeList::query()->whereIn('symbol',['BTC','ETH', 'SHIB', 'ADA', 'TRX', 'DOGE'])->get();
    }

    public static function getLimit($limit = 70)
    {
        return ExchangeList::query()->take($limit)->get();
    }
}
