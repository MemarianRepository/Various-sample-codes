<?php

namespace App\Models\Api\v1;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_location extends Model
{
    use HasFactory;

    public function user(){
        return $this->belongsTo(User::class, 'user_location_id', 'id');
    }
}
