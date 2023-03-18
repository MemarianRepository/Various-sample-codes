<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoneOrdersList extends Model
{
    use HasFactory;

    protected $table = 'done_orders_list';

    protected $guarded = [];

    public static function store($done_order_info)
    {
        self::query()->create($done_order_info);
    }

    public static function getLimitOrdersId()
    {
        return self::query()->where('order_type', 'limit')->get('identifier');
    }

    public static function getLimitOrderIdWithId($identifier)
    {
        return self::query()->where('identifier', $identifier)->first();
    }

    public static function updateDoneOrders($done_order)
    {
        $new_done_order = self::query()->where('identifier', $done_order['identifier'])->first();

        if ($new_done_order) {
            $new_done_order->update($done_order);
            return $new_done_order->order_id;
        } else
            return false;
        
    }

}
