<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KucoinCurrency extends Model
{
    use HasFactory;
    protected $table = 'kucoin_currencies';

    public static function getLastInfo()
    {
        return KucoinCurrency::query()->get()->last();
    }
}
