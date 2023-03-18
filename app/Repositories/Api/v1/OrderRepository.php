<?php


namespace App\Repositories\Api\v1;

use App\Models\Api\v1\OrdersList;

class OrderRepository
{
    public static function storeBuyOrders($orderInfo)
    {
        return OrdersList::query()->create($orderInfo);
    }

    public static function storeSellOrders($orderInfo)
    {
        return OrdersList::query()->create($orderInfo);
    }

    public static function updateOrders($order_id)
    {
        OrdersList::updateOrders($order_id);
    }
}
