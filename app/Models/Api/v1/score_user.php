<?php

namespace App\Models\Api\v1;

use App\Models\Api\v1\score;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class score_user extends Model
{
    use HasFactory;
    
  	public $timestamps = false;
  
	protected $fillable = ['score_id', 'user_id'];
    
    public function user(){
        return $this->belongsTo(User::class,'user_id','id');
    }

    public function score(){
        return $this->belongsTo(score::class,'score_id','id');
    }
}
