<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactUs extends Model
{
    use HasFactory;

    protected $table = 'contact-us';

    protected $fillable = ['title','description','address_url','phone_number','response_time','status'];
}
