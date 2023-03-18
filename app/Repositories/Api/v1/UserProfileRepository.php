<?php

namespace App\Repositories\Api\v1;

use App\Models\User;
use App\Repositories\BaseRepository;

/**
 * Class UserProfileRepository
 * @package App\Repositories\Api\v1
 * @version April 19, 2022, 3:11 pm +03
*/

class UserProfileRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
       
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
        return User::class;
    }
}
