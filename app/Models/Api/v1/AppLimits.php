<?php

namespace App\Models\Api\v1;

use Eloquent as Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class AppLimits
 * @package App\Models\V1
 * @version April 20, 2022, 2:54 pm +03
 *
 * @property boolean $level
 * @property string $description
 * @property integer $money_limitation
 * @property boolean $status
 * @property string $actions
 */
class AppLimits extends Model
{
    use SoftDeletes;

    use HasFactory;

    public $table = 'app_limits';
    
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';


    protected $dates = ['deleted_at'];



    public $fillable = [
        'level',
        'description',
        'money_limitation',
        'status',
        'actions'
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'level' => 'boolean',
        'description' => 'string',
        'money_limitation' => 'integer',
        'status' => 'boolean',
        'actions' => 'string'
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        'level' => 'nullable|boolean',
        'description' => 'nullable|string',
        'money_limitation' => 'nullable|integer',
        'status' => 'nullable|boolean',
        'actions' => 'nullable|string'
    ];

    
}
