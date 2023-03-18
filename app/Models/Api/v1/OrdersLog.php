<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdersLog extends Model
{
    use HasFactory;

    const MARKET_TYPE = '1';
    const LIMIT_TYPE = '2';

    const BUY_ROLE = '1';
    const SELL_ROLE = '2';

    const PENDING_STATUS = '0';
    const DONE_STATUS = '1';

    protected $guarded = [];

    protected $table = 'orders_log';

    public static function storeLog($order_log)
    {
        $new_order_log = new self;
        $new_order_log->order_id = @$order_log['order_id'];
        $new_order_log->user_id = $order_log['user_id'];
        $new_order_log->market = $order_log['market'];
        $new_order_log->type = $order_log['type'];
        $new_order_log->role = $order_log['role'];
        $new_order_log->order_price = $order_log['order_price'];
        $new_order_log->avg_price = $order_log['avg_price'];
        $new_order_log->amount = $order_log['amount'];
        $new_order_log->total_price = $order_log['total_price'];
        $new_order_log->current_wage = $order_log['current_wage'];
        $new_order_log->toman_wage = $order_log['toman_wage'];
        $new_order_log->filled= $order_log['filled'];
        $new_order_log->status = $order_log['status'];
        $new_order_log->request_values = @$order_log['request_values'];
        $new_order_log->exchange_error = $order_log['exchange_error'];
        $new_order_log->save();
    }

}
