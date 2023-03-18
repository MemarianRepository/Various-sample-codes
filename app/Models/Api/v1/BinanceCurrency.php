<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BinanceCurrency extends Model
{
    use HasFactory;
    protected $table = 'binance_currencies';

    public static function getLastInfo()
    {
        return BinanceCurrency::query()->get()->last();
    }
}
