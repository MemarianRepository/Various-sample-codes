<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpdateOrderLog extends Model
{
    use HasFactory;

    protected $table = 'update_orders_log';

    public static function store($data)
    {
        $Specified_time = date('Y-m-d', strtotime('-2 days'));
        self::query()->where('created_at', '<', $Specified_time)->delete();

        $update_orders = new self;
        $update_orders->data = $data;
        $update_orders->save();
    }

}
