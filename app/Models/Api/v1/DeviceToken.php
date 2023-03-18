<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;


class DeviceToken extends Model
{
    use SoftDeletes;

    use HasFactory;

    public $table = 'device_token';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    const STATUS_ACTIVATE  = 1;
    const STATUS_DEACTIVE = 0;


    protected $dates = ['deleted_at'];



    public $fillable = [
        'jwt_token',
        'device_id',
        'user_id',
        'status',
        'device_model',
        'firebase_token'
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'jwt_token' => 'string',
        'device_id' => 'string',
        'user_id' => 'integer',
        'status' => 'boolean',
        'device_model' => 'string',
        'firebase_token' => 'string'
    ];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     **/
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
