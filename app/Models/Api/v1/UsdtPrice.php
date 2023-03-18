<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsdtPrice extends Model
{
    use HasFactory;

    protected $table = 'usdt_price';

    public static function store($quantity)
    {
        // Get record if exist
        $usdt = self::query()->first();

        // Create new record
        if (!$usdt)
            $usdt = new self;

        $usdt->quantity = $quantity;
        $usdt->save();
    }

    public static function get()
    {
        return self::query()->first();
    }

}
