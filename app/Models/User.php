<?php

namespace App\Models;

use App\Helpers\CacheHelper;
use App\Models\Api\v1\Basket;
use App\Models\Api\v1\UserLink;
use App\Models\Api\v1\UserProduct;
use Laravel\Passport\HasApiTokens;
use App\Models\Api\v1\ProductWallet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    const SALAMASAL = 'SALAM';
    const EHYAKHAK = 'EHYAKHAK';
    const PARTNER = 'PARTNER';

    protected $fillable = [
        'name',
        'last_name',
        'mobile',
        'activation_code',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public static function store($name, $lastname, $email, $mobile, $partner_id)
    {
        $user = self::query()->where([
            'mobile' => $mobile,
            'status' => '1'
        ])->first();

        if (!$user) {
            $user = new self;
            $user->name = $name;
            $user->last_name = $lastname;
            $user->email = $email;
            $user->password = bcrypt('12345678');
            $user->mobile = $mobile;
            $user->partner_id = $partner_id;
            $user->reference_site = self::PARTNER;
            $user->status = '0';
            $user->save();
        }

        return $user->id;

    }

    public static function setIncremental($user_id, $amount, $type)
    {
        $user = self::query()->find($user_id);
        $user->incremental_amount = $amount;
        $user->incremental_type = $type;
        $user->save();
    }

    public static function setDecreasing($user_id, $amount, $type)
    {
        $user = self::query()->find($user_id);
        $user->decreasing_amount = $amount;
        $user->decreasing_type = $type;
        $user->save();
    }

    public static function emptyIncremental($user_id)
    {
        $user = self::query()->find($user_id);
        $user->incremental_amount = null;
        $user->incremental_type = null;
        $user->save();
    }

    public static function emptyDecreasing($user_id)
    {
        $user = self::query()->find($user_id);
        $user->decreasing_amount = null;
        $user->decreasing_type = null;
        $user->save();
    }

    public static function getIncremental($user_id)
    {
        return self::query()->find($user_id, ['incremental_amount', 'incremental_type', 'partner_id', 'id']);
    }

    public static function getDecreasing($user_id)
    {
        return self::query()->find($user_id, ['decreasing_amount', 'decreasing_type', 'id']);
    }

    public function basket(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Basket::class)->latestOfMany();
    }

    public function baskets()
    {
        return $this->hasMany(Basket::class, 'user_id', 'id')->where('status', '0');
    }

    public static function changeStatus($id)
    {
        $user = self::query()->find($id);
        $user->status = '1';
        $user->save();
    }

    // Check user activation code
    public static function checkActivationCode($activation_code, $mobile)
    {
        // Check expire time of activation code
        if (CacheHelper::checkCache($activation_code)) {
            return User::query()
                ->where('activation_code', $activation_code)
                ->where('mobile', $mobile)
                ->first();
        }
    }

    // Update new password of user
    public static function updateNewPassword($mobile, $new_password)
    {
        $user = User::query()->where('mobile', $mobile)->first();
        $user->password = bcrypt($new_password);
        $user->save();
    }

    public static function checkActivationCodeWithoutTime($activation_code, $status)
    {
        return User::query()
            ->where('activation_code', $activation_code)
            ->where('status', $status)
            ->first();
    }

    public static function insertUserMobile($mobile): bool
    {
        $user = User::query()
            ->where('mobile', $mobile)
            ->first();
        if (empty($user)){
            $user = new User;
            $user->mobile = $mobile;
            $user->save();
            return true;
        }
        elseif ($user->status == 0 || $user->status == null) {
            $user->mobile = $mobile;
            $user->save();
            return true;
        }
        return false;
    }

    public static function insertUserInfo($user, $name, $lastname)
    {
        $user->name = $name;
        $user->last_name = $lastname;
        $user->save();
    }

    public static function insertUserPassword($user, $password, $reference_site)
    {
        $user->reference_site = $reference_site;
        $user->password = bcrypt($password);
        $user->status = '1';
        $user->save();
    }

    public static function checkUserPassword($mobile, $password)
    {
        $user = User::query()->where('mobile', $mobile)->first('password');
        if($user)
            if(Hash::check($password, $user->password))
                return true;
    }

    public static function findUserByMobile($mobile)
    {
        return User::query()->where('mobile', $mobile)->first();
    }

    public static function updateUserMobile($old_mobile, $new_mobile): bool
    {
        $mobile_exist = self::query()->where('mobile', $new_mobile)->where('status', '1')->first();
        if ($mobile_exist)
            return true;
        else {

            $user = self::query()->where('mobile', $old_mobile)->first();
            $user->mobile = $new_mobile;
            $user->save();
            return false;
        }

    }

    public function user_product()
    {
        $this->hasMany(UserProduct::class,'user_id');
    }

    public static function checkUserExist($mobile)
    {
        return User::query()->where('mobile', $mobile)->where('status', '1')->first();
    }

    public static function find($id)
    {
        return self::query()->find($id);
    }


}
