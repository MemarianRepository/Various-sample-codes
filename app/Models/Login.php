<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Login extends Model
{
    use HasFactory;

    protected $fillable =
        [
            'user_id',
            'ip_address',
            'device_name'
        ];

    protected $table = 'login_history';

}
