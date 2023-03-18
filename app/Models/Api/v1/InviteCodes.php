<?php

namespace App\Models\Api\v1;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InviteCodes extends Model
{
    use HasFactory;

    protected $table = 'invite_codes';

    protected $fillable = [
        'user_id',
        'user_percent',
        'code'
    ];

    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function commissions(){
        return $this->hasMany(commision::class, 'invite_code_id', 'id');
    }
}
