<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ManualDeposit extends Model
{
    use HasFactory;
    protected $table = 'manual_deposits';
    protected $fillable = [
        'transaction_code',
         'time',
         'exchange_id',
         'amount',
         'user_id',
         'tracking_id'
    ];

    public static function GetFilteredResult($symbol = null, $from_date = null, $to_date = null, $tracking_id = null,$user_id){
        if($symbol != null){
            $exchange_id = ExchangeList::where('symbol',$symbol)->first()->id;
        }
        $query = ManualDeposit::query();
        $query->where('user_id',$user_id);
        if($symbol != null){
            $query->where('exchange_id',$exchange_id);
        }
        if($from_date != null && $to_date != null){
            $query->where('created_at','>=',$from_date)->where('created_at','<=',$to_date);
        }
        if($tracking_id != null){
            $query->where('tracking_id',$tracking_id);
        }
        return $query->get();
    }

    public static function storeDeposits($user_id, $wallet_address, $exchange_id, $amount, $transaction_code)
    {
        $deposits = new self();
        $deposits->user_id = $user_id;
        $deposits->wallet_address = $wallet_address;
        $deposits->exchange_id = $exchange_id;
        $deposits->amount = $amount;
        $deposits->transaction_code = $transaction_code;
        $deposits->tracking_id = rand(100000, 999999);
        $deposits->save();
    }

    public static function storeAutomaticDeposits($transaction_code)
    {
        $deposit = new self();
        $deposit->user_id = Auth::guard('api')->id();
        $deposit->transaction_code = $transaction_code;
        $deposit->save();
    }

    public static function getDeposits()
    {
        return self::query()->where('status', '0')->get();
    }

    public static function updateDepositStatus($id, $deposit_status = false)
    {
        if ($deposit_status) {
            switch ($deposit_status) {
                case 'processing':
                    $deposit = self::query()->find($id);
                    $deposit->status = '0';
                    $deposit->save();
                    break;
                case 'canceled':
                    $deposit = self::query()->find($id);
                    $deposit->status = '-1';
                    $deposit->save();
                    break;
            }

        } else {
            $deposit = self::query()->find($id);
            $deposit->status = '1';
            $deposit->save();
        }

    }

    public function exchange(){
        return $this->belongsTo(ExchangeList::class, 'exchange_id', 'id');
    }
}
