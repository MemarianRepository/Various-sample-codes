<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionsLimit extends Model
{
    use HasFactory;

    protected $table = 'transactions_limit';
    protected $guarded = [];

    const DAILY = 'daily';
    const MONTHLY = 'monthly';
    const DEPOSIT = 'deposit';
    const WITHDRAWAL = 'withdrawal';

    public static function getByAccessLevel($access_level, $type)
    {
        return self::query()->where('access_level', $access_level)->where('type', $type)->get();
    }

}
