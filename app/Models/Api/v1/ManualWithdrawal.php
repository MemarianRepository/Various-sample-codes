<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualWithdrawal extends Model
{
    use HasFactory;
    protected $table = 'manual_withdraws';
    protected $fillable = [
        'recipient_address',
        'amount',
        'exchange_id',
        'user_id',
        'tracking_id'
    ];

    public static function GetFilteredResult($symbol = null, $from_date = null, $to_date = null, $tracking_id = null,$user_id){
        if($symbol != null){
            $exchange_id = ExchangeList::where('symbol',$symbol)->first()->id;
        }
        $query = ManualWithdrawal::query();
        $query->where('user_id',$user_id);
        if($symbol != null){
            $query->where('exchange_id',$exchange_id);
        }
        if($from_date != null && $to_date != null){
            $query->whereDate('created_at','>=',$from_date)->whereDate('created_at','<=',$to_date);
        }
        if($tracking_id != null){
            $query->where('tracking_id',$tracking_id);
        }
        return $query->get();
    }

    public static function storeWithdrawals($user_id, $wallet_address, $exchange_id, $amount)
    {
        $withdraws = new self();
        $withdraws->user_id = $user_id;
        $withdraws->wallet_address = $wallet_address;
        $withdraws->exchange_id = $exchange_id;
        $withdraws->amount = $amount;
        $withdraws->tracking_id = rand(100000, 999999);
        $withdraws->save();
    }

    public function exchange(){
        return $this->belongsTo(ExchangeList::class, 'exchange_id', 'id');
    }
}
