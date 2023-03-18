<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    use HasFactory;

    protected $table = 'blogs';

  protected $fillable = ['title','description','image','type','status'];
}
