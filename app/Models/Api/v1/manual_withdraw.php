<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class manual_withdraw extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient_address',
        'amount',
        'exchange_id',
        'user_id',
        'tracking_id'
    ];
}
