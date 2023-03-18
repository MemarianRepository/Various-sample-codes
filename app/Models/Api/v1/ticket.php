<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'subject_id',
        'user_id'
    ];

    public function messages(){
        return $this->hasMany(ticket_message::class, 'ticket_id', 'id');
    }

    public function subject(){
        return $this->belongsTo(department_subject::class, 'subject_id', 'id');
    }
}