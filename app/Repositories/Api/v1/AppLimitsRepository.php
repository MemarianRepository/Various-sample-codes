<?php

namespace App\Repositories\V1;

use App\Models\V1\AppLimits;
use App\Repositories\BaseRepository;

/**
 * Class AppLimitsRepository
 * @package App\Repositories\V1
 * @version April 20, 2022, 2:54 pm +03
*/

class AppLimitsRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'level',
        'description',
        'money_limitation',
        'status',
        'actions'
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
        return AppLimits::class;
    }
}
