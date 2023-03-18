<?php


namespace App\Repositories\Api\v1;


use App\Helpers\CacheHelper;
use App\Models\User;
use http\Env\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    // Increase user credit
    public static function increaseCredit($amount, $user_id = null)
    {
        if ($user_id)
            $user = User::query()->find($user_id);
        else
            $user = User::query()->find(Auth::guard('api')->id());

        $user->increase_amount = $amount;
        $user->save();
    }

    public static function increaseCurrency($amount)
    {
        $user = User::query()->find(Auth::guard('api')->id());
        $user->increase_currency = $amount;
        $user->save();
    }

    public static function increaseCurrencyType($amount)
    {
        $user = User::query()->find(Auth::guard('api')->id());
        $user->incremental_currency_type = $amount;
        $user->save();
    }

    // Get user credit
    public static function getIncreaseCredit($user_id = null)
    {
        if ($user_id)
            $user = User::query()->find($user_id, ['increase_amount']);
        else
            $user = User::query()->find(Auth::guard('api')->id(), ['increase_amount']);

        return $user->increase_amount;
    }

    public static function getIncreaseCreditWithAuth()
    {
        $user = User::query()->find(Auth::id(), ['increase_amount']);
        return $user->increase_amount;
    }


    public static function getIncreaseCurrency()
    {
        $user = User::query()->find(Auth::guard('api')->id(), ['increase_currency']);
        if ($user)
            return $user->increase_currency;
    }

    public static function getIncrementalCurrencyType()
    {
        $user = User::query()->find(Auth::guard('api')->id(), ['incremental_currency_type']);
        if ($user)
            return $user->incremental_currency_type;
    }

    // Check user activation code
    public static function checkActivationCode($activation_code, $status)
    {
        // Check expire time of activation code
        if(CacheHelper::checkCache($activation_code)){
            return User::query()
                ->where('activation_code', $activation_code)
                ->where('status', $status)
                ->first();
        }
    }

    public static function insertUserInfo($mobile, $password, $username): bool
    {
        $user = User::query()
            ->where('mobile', $mobile)
            ->first();
        if (empty($user)){
            $user = new User;
            $user->username = $username;
            $user->mobile = $mobile;
            $user->password = bcrypt($password);
            $user->save();
            return true;
        }
        elseif ($user->status == 0 || $user->status == null) {
            $user->username = $username;
            $user->mobile = $mobile;
            $user->password = bcrypt($password);
            $user->save();
            return true;
        }
        return false;
    }

    public static function checkUserExist($mobile, $password)
    {
        $user = User::query()->where([
            'status' => 1,
            'mobile' => $mobile,
        ])->first();
        if($user)
            if(Hash::check($password, $user->password))
                return $user;
    }

    public static function checkUserPassword($mobile, $password): bool
    {
        $user = User::query()->where('mobile', $mobile)->first('password');
        if($user)
            if(Hash::check($password, $user->password))
                return true;
    }

    // Update new password of user
    public static function updateNewPassword($mobile, $new_password)
    {
        $user = User::query()->where('mobile', $mobile)->first();
        $user->password = bcrypt($new_password);
        $user->save();
    }

    // Insert user secret key for google authenticator
    public static function insertSecretKey($user_id, $secret_key)
    {
        $user = User::query()->find($user_id);
        $user->google2fa_secret = $secret_key;
        $user->save();
    }

    // Get login type of user
    public static function getLoginType($mobile)
    {
        return User::query()->where('mobile', $mobile)->first('login_type');
    }

    // Update new value of login type
    public static function updateLoginType($mobile, $login_type)
    {
        $user = User::query()->where('mobile', $mobile)->first();
        $user->login_type = $login_type;
        $user->save();
    }

    public static function setFinancialID($card_id)
    {
        $user = User::query()->find(Auth::guard('api')->id());
        if (!$user->financial_id) {
            $user->financial_id = $card_id;
            $user->save();
        }
    }

    public static function disableTwoFactorLogin($user_id)
    {
        $user = User::query()->find($user_id);
        $user->login_type = 0;
        $user->save();
    }

    public static function checkUserExistWithTwoParameters($username, $mobile)
    {
        $username = User::where([
            'username' => $username,
            'status' => '1'
        ])->first();

        $mobile = User::where([
            'mobile' => $mobile,
            'status' => '1'
        ])->first();

        $errors = [];

        if ($username)
            $errors ['username']= 'username already exist';

        if ($mobile)
            $errors ['mobile']= 'mobile already exist';

        if ($username || $mobile)
            return $errors;
        else
            return false;
    }

    public static function findUserByMobile($mobile)
    {
        return User::query()->where('mobile', $mobile)->first();
    }

}
