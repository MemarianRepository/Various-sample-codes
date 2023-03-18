<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class level_two_user_doc extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'home_phone_number',
        'verification_image',
        'user_upgrade_id',
        'province',
        'city',
        'postal_code',
        'verification_code',
        'expected_code'
    ];
}
