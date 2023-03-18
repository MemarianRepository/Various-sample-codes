<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoinexMarketInfoExtracted extends Model
{
    use HasFactory;

    protected $table = 'coinex_market_info_extracted';

    public static function extractMarketInfo($symbol, $data)
    {
        if ($symbol && $data) {
            $market_info = self::query()->where('symbol', $symbol)->first();
            $market_info->symbol = $symbol;
            $market_info->data = json_encode($data);
            $market_info->save();

        }
    }

    public static function deleteMarketInfoExtract()
    {
        self::query()->where('created_at')->truncate();
    }

    public static function findMarketInfo($symbol)
    {
        return self::query()->where('symbol', $symbol)->first();
    }
}
