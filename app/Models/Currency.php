<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    public static function getLastInfo()
    {
        return Currency::query()->orderBy('id', 'desc')->first();
    }

    public static function store($data)
    {
        $Specified_time = date('Y-m-d', strtotime('-2 days'));
        self::query()->where('created_at', '<', $Specified_time)->delete();

        $currency = new self;
        $currency->data = $data;
        $currency->save();
    }

}
