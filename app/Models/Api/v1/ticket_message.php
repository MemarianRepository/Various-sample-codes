<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ticket_message extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'message',
        'image_url',
        'is_seen',
        'ticket_id',
        'user_id'
    ];

    public function admin(){
        return $this->hasOne(User::class);
    }
    public function ticket(){
        return $this->belongsTo(Tickets::class);
    }
}
