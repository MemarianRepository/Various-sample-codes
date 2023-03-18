<?php

namespace App\Models\Api\v1;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exchange_id',
        'amount',
        'wallet',
        'status'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public static function findByType($user_id, $type)
    {
        return self::query()->where('user_id', $user_id)->where('wallet', $type)->first();
    }

}
