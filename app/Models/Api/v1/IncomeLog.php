<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeLog extends Model
{
    use HasFactory;

    protected $table = 'income_log';
    protected $guarded = [];

    const BUY_TYPE = 1;
    const SELL_TYPE = 2;

    public static function store($log)
    {
        $income_log = new self;
        $income_log->user_id = $log['user_id'];
        $income_log->order_id = $log['order_id'];
        $income_log->wage = $log['wage'];
        $income_log->type = $log['type'];
        $income_log->save();
    }
}
