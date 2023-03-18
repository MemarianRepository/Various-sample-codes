<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoreExchange extends Model
{
    use HasFactory;

    protected $table = 'score_exchanges';

    protected $fillable = [
        'user_id',
        'score',
        'status',
        'description'
    ];
}
