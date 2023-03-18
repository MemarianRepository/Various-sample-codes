<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserUpgrade extends Model
{
  use HasFactory;

  protected $table = 'user_upgrades';
  // protected $dateFormat = 'U';
  public $timestamps = false;

  protected $fillable = [
      'user_id',
      'created_at',
      'updated_at',
      'request_level',
      'status',
      'level_one_user_docs_id',
      'level_two_user_docs_id'
  ];
  
  public function level_two_user_doc(){
        return $this->hasMany(level_two_user_doc::class,'user_upgrade_id','id');
    }
  public function level_one_user_doc(){
    return $this->hasMany(level_one_user_doc::class, 'user_upgrade_id', 'id');
  }


}
