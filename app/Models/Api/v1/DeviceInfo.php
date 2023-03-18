<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceInfo extends Model
{
    protected $table = 'device_info';

    public static function storeDevicesInfo($device_token, $device_type)
    {
        $device = self::query()->where('device_token', $device_token)->first();
        if(!$device){
            $device = new self();
            $device->device_token = $device_token;
            $device->device_type = $device_type;
            $device->save();
        }
    }
}
