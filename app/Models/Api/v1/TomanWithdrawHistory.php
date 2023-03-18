<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TomanWithdrawHistory extends Model
{
    use HasFactory;

    protected $table = 'toman_withdraw_history';
    protected $guarded = [];

    public static function getWithoutFilter($skip = null, $number_of_records, $user_id)
    {
        if($skip)
        {
            return self::query()
                ->where('user_id', $user_id)
                ->skip($skip)->take($number_of_records)
                ->orderBy('id','desc')->get();
        }else{
            return self::query()
                ->where('user_id', $user_id)
                ->take($number_of_records)
                ->orderBy('id','desc')->get();
        }
    }

    public static function getCountOfPages($user_id, $number_of_records)
    {
        $count = self::query()->where('user_id', $user_id)->count();
        return round($count / $number_of_records);
    }

    public static function searchByDate($reference_id = null, $from_date, $to_date, $user_id)
    {
        if ($reference_id) {
            return self::query()->where('user_id', $user_id)
                ->where('reference_id', $reference_id)
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date)->orderBy('id', 'desc')->get();
        } else {
            return self::query()->where('user_id', $user_id)
                ->whereDate('created_at', '>=', $from_date)
                ->whereDate('created_at', '<=', $to_date)->orderBy('id', 'desc')->get();
        }

    }

    public static function searchWithoutDate($reference_id, $user_id)
    {
        return self::query()->where('user_id', $user_id)
            ->where('reference_id', $reference_id)->orderBy('id', 'desc')->get();
    }

    public static function sumBetweenTwoDate($user_id, $from_date, $to_date)
    {
        return self::query()->where('user_id', $user_id)->whereBetween('created_at', [$from_date, $to_date])->where('status', '!=', -1)->sum('quantity');
    }

    public static function today($today, $user_id)
    {
        return self::query()->where('user_id', $user_id)->whereDate('created_at', $today)->sum('quantity');
    }

}
