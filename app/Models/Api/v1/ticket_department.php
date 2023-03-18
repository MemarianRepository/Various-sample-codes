<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ticket_department extends Model
{
    use HasFactory;

    public function subjects(){
        return $this->hasMany(department_subject::class, 'department_id', 'id');
    }
}
