<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepositSetting extends Model
{
    use HasFactory;

    public static function getDepositSetting()
    {
        return DepositSetting::query()->first('mode');
    }
}
