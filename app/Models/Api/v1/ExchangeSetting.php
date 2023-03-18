<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeSetting extends Model
{
    use HasFactory;

    public static function getExchangeSettings()
    {
        return ExchangeSetting::query()->first('exchange');
    }
}
