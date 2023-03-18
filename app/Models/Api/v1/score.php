<?php

namespace App\Models\api\v1;

use App\Models\Api\v1\score_user;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class score extends Model
{
    use HasFactory;

    public function user(){
        return $this->hasMany(score_user::class, 'user_id', 'id');
    }

    public static function get()
    {
        return self::query()->get();
    }

}
