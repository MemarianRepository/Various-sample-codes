<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class department_subject extends Model
{
    use HasFactory;

    public function department(){
        return $this->belongsTo(ticket_department::class, 'department_id', 'id');
    }
}
