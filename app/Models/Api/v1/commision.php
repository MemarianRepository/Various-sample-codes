<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class commision extends Model
{
    use HasFactory;

    protected $fillable = [
        'sub_user_id',
        'head_user_id',
        'currency_id' ,
        'order_commision_amount',
        'sub_user_share',
        'head_user_share',
        'invite_code_id'
    ];
    
    public function currency(){
        return $this->hasOne(ExchangeList::class, 'id', 'currency_id');
    }
}
