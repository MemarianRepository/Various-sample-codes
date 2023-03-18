<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsCreditLog extends Model
{
    use HasFactory;

    protected $table = 'sms_credit_log';

    public static function store($send_status)
    {
        $sms_credit_log = self::query()->first();
        if ($sms_credit_log)
            $sms_credit_log->send_status = $send_status;
        else
            $sms_credit_log = new self;

        $sms_credit_log->save();
    }

    public static function get()
    {
        $sms_credit_log =  self::query()->first();
        if ($sms_credit_log)
            return $sms_credit_log;
        else {
            $sms_credit_log = new self;
            $sms_credit_log->send_status = '0';
            $sms_credit_log->save();
            return $sms_credit_log;
        }

    }
}
