<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class level_one_user_doc extends Model
{
    use HasFactory;

    protected $fillable = [
        'national_card_image',
        'first_name',
        'last_name',
        'gender',
        'birth_date',
        'national_card_number',
        'bank_name',
        'card_number',
        'shaba_number',
        'user_upgrade_id'
    ];
}
