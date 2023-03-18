<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoinexCreditLog extends Model
{
    use HasFactory;

    protected $table = 'coinex_credit_log';

    public static function store($send_status)
    {
        $coinex_credit_log = self::query()->first();
        if ($coinex_credit_log)
            $coinex_credit_log->send_status = $send_status;
        else
            $coinex_credit_log = new self;

        $coinex_credit_log->save();
    }

    public static function get()
    {
        $coinex_credit_log =  self::query()->first();
        if ($coinex_credit_log)
            return $coinex_credit_log;
        else {
            $coinex_credit_log = new self;
            $coinex_credit_log->send_status = '0';
            $coinex_credit_log->save();
            return $coinex_credit_log;
        }

    }
}
