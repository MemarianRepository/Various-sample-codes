<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class invited_user extends Model
{
    use HasFactory;
    
    protected $table = 'invited_users';
    
    protected $fillable = [
        'base_user',
        'invited_user',
        'invite_code_id'
    ];
    
    public function base_user(){
        return $this->belongsTo(User::class, 'base_user', 'id');
    }

    public function invited_user(){
        return $this->belongsTo(User::class, 'invited_user', 'id');
    }
}
