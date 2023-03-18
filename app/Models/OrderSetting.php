<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSetting extends Model
{
    use HasFactory;

    public $table = 'order_settings';

    const AUTOMATIC_MODE = 1;
    const CUSTOM_MODE = 2;

    public static function checkOrderMode()
    {
        return OrderSetting::query()->where('name', 'ارسال سفارش')->first('mode');
    }
}
