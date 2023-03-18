<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsoleLog extends Model
{
    use HasFactory;

    protected $table = 'console_log';

    public static function store($command_name, $error)
    {
        $console = new self;
        $console->name = $command_name;
        $console->error = $error;
        $console->save();
    }

}
