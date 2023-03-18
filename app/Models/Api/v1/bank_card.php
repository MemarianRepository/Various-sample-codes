<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class bank_card extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_name',
        'number',
        'shaba',
        'user_id'
    ];
}
