<?php

namespace App\Repositories\Api\v1;

use App\Models\Api\v1\DeviceToken;
use App\Repositories\BaseRepository;

/**
 * Class DeviceTokenRepository
 * @package App\Repositories\Api\v1
 * @version April 19, 2022, 3:11 pm +03
*/

class DeviceTokenRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'jwt_token',
        'device_id',
        'user_id',
        'status',
        'device_model',
        'firebase_token'
    ];

    /**
     * Return searchable fields
     *
     * @return array
     */
    public function getFieldsSearchable()
    {
        return $this->fieldSearchable;
    }

    /**
     * Configure the Model
     **/
    public function model()
    {
        return DeviceToken::class;
    }
}
