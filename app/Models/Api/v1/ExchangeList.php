<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Model;

class ExchangeList extends Model
{
    protected $table = 'exchange_list';

    public function wallet(){
        return $this->belongsTo(UserWallets::class);
    }

    public static function getCurrencies()
    {
        return self::query()->where('coinex', '1')->get();
    }

    public static function updateSmartContractName($currency_symbol, $smart_contract_name)
    {
        $currency = self::query()->where('symbol', $currency_symbol)->first();
        $currency->smart_contract_name = $smart_contract_name;
        $currency->save();
    }

    public static function findCurrency($symbol)
    {
        return self::query()->where('symbol' , $symbol)->first('smart_contract_name');
    }

    public static function findBySymbol($symbol)
    {
        return self::query()->where('symbol', $symbol)->first();
    }
}
