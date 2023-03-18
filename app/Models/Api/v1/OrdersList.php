<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OrdersList extends Model
{
    use HasFactory;

    const MARKET_TYPE = '1';
    const LIMIT_TYPE = '2';

    const BUY_ROLE = '1';
    const SELL_ROLE = '2';

    const CANCEL_STATUS = '-1';
    const PENDING_STATUS = '0';
    const DONE_STATUS = '1';
    const PART_DEAL = '2';


    protected $guarded = [];

    protected $table = 'orders_list';

    public function doneOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return self::belongsTo(DoneOrdersList::class, 'order_id', 'id');
    }

    public static function findById($order_id)
    {
        return self::query()->find($order_id);
    }

    public static function cancelOrder($order_id)
    {
        $order = self::query()->find($order_id);
        $order->status = self::CANCEL_STATUS;
        $order->save();
    }

    public static function getOrderListWithoutFilter($skip = null, $number_of_records, $user_id)
    {
        if($skip)
        {
            return OrdersList::query()->where('user_id', $user_id)
                ->skip($skip)->take($number_of_records)
                ->orderBy('id','desc')
                ->where('status', '!=', '-1')
                ->orderBy('id', 'desc')->get();
        }else{
            return OrdersList::query()->where('user_id', $user_id)
                ->take($number_of_records)
                ->orderBy('id','desc')
                ->where('status', '!=', '-1')
                ->orderBy('id', 'desc')->get();
        }
    }

    public static function getCountOfPages($user_id, $number_of_records)
    {
        $count = OrdersList::query()->where('user_id', $user_id)->count();
        return round($count / $number_of_records);
    }

    public static function countOfPagesByDate($pair = null, $type = null, $role = null, $from_date , $to_date, $user_id, $number_of_records)
    {
        if ($pair && $type && $role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('market', $pair)
                ->where('type', $type)
                ->where('role', $role)
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date)
                ->count();
        }

        if (!$pair && !$type && !$role) {
            $count = self::query()->where('user_id', $user_id)
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date)
                ->count();
        }

        if ($pair && !$type && !$role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('market', $pair)
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date)
                ->count();
        }

        if ($pair && $type && !$role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('market', $pair)
                ->where('type', $type)
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date)
                ->count();
        }

        if ($pair && !$type && $role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('market', $pair)
                ->where('role', $role)
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date)
                ->count();
        }

        if (!$pair && $type && $role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('type', $type)
                ->where('role', $role)
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date)
                ->count();
        }

        if (!$pair && $type && !$role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('type', $type)
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date)
                ->count();
        }

        if (!$pair && !$type && $role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('role', $role)
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date)
                ->count();
        }

        return round($count / $number_of_records);
    }

    public static function countOfPagesWithoutDate($pair = null, $type = null, $role = null, $user_id, $number_of_records)
    {
        if ($pair && $type && $role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('market', $pair)
                ->where('type', $type)
                ->where('role', $role)
                ->count();
        }

        if (!$pair && !$type && !$role) {
            $count = self::query()->where('user_id', $user_id)->count();
        }

        if ($pair && !$type && !$role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('market', $pair)->count();
        }

        if ($pair && $type && !$role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('market', $pair)
                ->where('type', $type)
                ->count();
        }

        if ($pair && !$type && $role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('market', $pair)
                ->where('role', $role)
                ->count();
        }

        if (!$pair && $type && $role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('type', $type)
                ->where('role', $role)
                ->count();
        }

        if (!$pair && $type && !$role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('type', $type)
                ->count();
        }

        if (!$pair && !$type && $role) {
            $count = self::query()->where('user_id', $user_id)
                ->where('role', $role)
                ->count();
        }

        return round($count / $number_of_records);
    }

    public static function updateOrders($order_id)
    {
        $order = self::query()->find($order_id);
        $order->filled = 100;
        $order->status = self::DONE_STATUS;
        $order->save();
    }

    public static function updateLimitOrders($order_id, $status, $filled)
    {
        $order = self::query()->find($order_id);
        $order->filled = $filled;
        $order->status = $status;
        $order->save();
    }

    public static function searchByDate($skip = null, $number_of_records, $pair = null, $type = null, $role = null, $from_date , $to_date, $user_id )
    {
        if ($skip) {

            if ($pair && $type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('market', $pair)
                    ->where('type', $type)
                    ->where('role', $role)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && !$type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if ($pair && !$type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('market', $pair)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if ($pair && $type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('market', $pair)
                    ->where('type', $type)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if ($pair && !$type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('market', $pair)
                    ->where('role', $role)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && $type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('type', $type)
                    ->where('role', $role)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && $type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('type', $type)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && !$type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('role', $role)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

        } else {

            if ($pair && $type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('market', $pair)
                    ->where('type', $type)
                    ->where('role', $role)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && !$type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if ($pair && !$type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('market', $pair)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if ($pair && $type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('market', $pair)
                    ->where('type', $type)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if ($pair && !$type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('market', $pair)
                    ->where('role', $role)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && $type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('type', $type)
                    ->where('role', $role)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && $type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('type', $type)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && !$type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('role', $role)
                    ->whereDate('created_at', '>=', $from_date)
                    ->whereDate('created_at', '<=', $to_date)
                    ->orderBy('id', 'desc')->get();
            }
        }

    }

    public static function searchWithoutDate($skip, $number_of_records, $pair = null, $type = null, $role = null, $user_id)
    {
        if ($skip) {

            if ($pair && $type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('market', $pair)
                    ->where('type', $type)
                    ->where('role', $role)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && !$type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)->orderBy('id', 'desc')->get();
            }

            if ($pair && !$type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('market', $pair)->orderBy('id', 'desc')->get();
            }

            if ($pair && $type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('market', $pair)
                    ->where('type', $type)
                    ->orderBy('id', 'desc')->get();
            }

            if ($pair && !$type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('market', $pair)
                    ->where('role', $role)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && $type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('type', $type)
                    ->where('role', $role)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && $type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('type', $type)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && !$type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->skip($skip)->take($number_of_records)
                    ->where('role', $role)
                    ->orderBy('id', 'desc')->get();
            }

        } else {

            if ($pair && $type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('market', $pair)
                    ->where('type', $type)
                    ->where('role', $role)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && !$type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)->orderBy('id', 'desc')->get();
            }

            if ($pair && !$type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('market', $pair)->orderBy('id', 'desc')->get();
            }

            if ($pair && $type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('market', $pair)
                    ->where('type', $type)
                    ->orderBy('id', 'desc')->get();
            }

            if ($pair && !$type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('market', $pair)
                    ->where('role', $role)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && $type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('type', $type)
                    ->where('role', $role)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && $type && !$role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('type', $type)
                    ->orderBy('id', 'desc')->get();
            }

            if (!$pair && !$type && $role) {
                return self::query()->where('user_id', $user_id)
                    ->take($number_of_records)
                    ->where('role', $role)
                    ->orderBy('id', 'desc')->get();
            }

        }

    }
}

