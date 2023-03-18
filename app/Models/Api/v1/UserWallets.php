<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

///**
// * Class UserWallets
// * @package App\Models\V1
// * @version April 19, 2022, 3:40 pm +03
// *
// * @property \App\Models\V1\ExchangeList $exchange
// * @property \App\Models\V1\User $user
// * @property integer $user_id
// * @property integer $exchange_id
// * @property integer $amount
// * @property string $wallet
// * @property boolean $status
// */
class UserWallets extends Model
{
    use SoftDeletes;

    use HasFactory;

    public $table = 'user_wallets';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';


    protected $dates = ['deleted_at'];



    public $fillable = [
        'user_id',
        'exchange_id',
        'amount',
        'wallet',
        'status'
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'exchange_id' => 'integer',
        'amount' => 'double',
        'wallet' => 'string',
        'status' => 'boolean'
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        'user_id' => 'required|integer',
        'exchange_id' => 'nullable|integer',
        'amount' => 'nullable|numeric',
        'wallet' => 'nullable|string|max:255',
        'status' => 'nullable|boolean',
        'created_at' => 'nullable|integer',
        'updated_at' => 'nullable|integer'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     **/
    public function exchange()
    {
        return $this->belongsTo(ExchangeList::class, 'exchange_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     **/
    public function user()
    {
        return $this->belongsTo(\App\Models\V1\User::class, 'user_id');
    }

    public static function isExist(int $exchangeId):bool{
        $model = UserWallets::where('exchange_id' , $exchangeId)->where('user_id' , Auth::id())->first();
       // dd($model);
        if(is_null($model)){
            return false;
        }
        return true;
    }

}
