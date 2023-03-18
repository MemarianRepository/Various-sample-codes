<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\User;
use App\Helpers\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use Elegant\Sanitizer\Sanitizer;
use App\Http\Controllers\Controller;
use App\Notifications\LoginNotification;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Http\Resources\Api\v1\UserResource;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $santizier = new Sanitizer($request->all(), [
            'mobile' => 'strip_tags',
            'activation_code' => 'strip_tags',
            'password' => 'strip_tags',
        ]);

        $request_sanitized = $santizier->sanitize();

        $validator = Validator::make($request_sanitized, [
            'mobile' => ['digits:11', 'numeric'],
            'activation_code' => ['digits:5'],
            'password' => [Password::min(8)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            return Response::failed($validator->errors()->toArray(), null, 422, -1);
        }

        if ($request->has(['mobile', 'password'])) {

            $check_user_password = User::checkUserPassword($request->mobile, $request->password);

            if ($check_user_password) {

                SmsHelper::sendActivationCode($request->mobile);
                return Response::success('کد تایید برای شما ارسال گردید', null);

            } else
                return Response::failed('رمز عبور یا شماره موبایل اشتباست', null, 422, -4);

        }

        if ($request->has(['mobile', 'activation_code'])) {

            $check_activation_code = User::checkActivationCode($request->activation_code, $request->mobile);

            if ($check_activation_code) {

                $user = User::findUserByMobile($request->mobile);
                auth()->loginUsingId($user->id);
                $user = auth()->user();
                Passport::personalAccessTokensExpireIn(Carbon::now()->addWeeks(1));
                $tokenResult = $user->createToken('userToken');
                $tokenModel = $tokenResult->token;
                $tokenModel->save();
                $data = [
                    'user' => new UserResource($user),
                    'token' => $tokenResult->accessToken,
                    'token_type' => 'Bearer'
                ];
                Notification::send($user, new LoginNotification($user,'ورود','ورود شما با موفقیت انجام شد'));

                return Response::success('به خانه عسل خوش آمدید', $data);

            } else
                return Response::failed('کد تایید اشتباست، لطفا مجددا اقدام نمایید', null, 422, -4);

        }


    }

    public function register(Request $request)
    {
        $santizier = new Sanitizer($request->all(), [
            'mobile' => 'strip_tags',
            'activation_code' => 'strip_tags',
            'password' => 'strip_tags',
        ]);

        $request_sanitized = $santizier->sanitize();

        $validator = Validator::make($request_sanitized, [
            'mobile' => ['digits:11', 'numeric'],
            'activation_code' => ['digits:5'],
            'password' => [Password::min(8)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            return Response::failed($validator->errors()->toArray(), null, 422, -1);
        }


        // Step three
        if ($request->has(['mobile', 'name', 'lastname', 'activation_code'])) {

            $check_activation_code = User::checkActivationCodeWithoutTime($request->activation_code, '0');
            if ($check_activation_code) {

            $user = User::findUserByMobile($request->mobile);
            if ($user) {

                User::insertUserInfo($user, $request->name, $request->lastname);
                return Response::success('اطلاعات ثبت گردید', null);

            } else
                return Response::failed('لطفا از مرحله اول مجددا اقدام به ثبت نام نمایید', null, 422, -3);

            } else
                return Response::failed('لطفا مراحل ثبت نام را به درستی طی نمایید', null, 422, -4);

        }

        // Step four
        if ($request->has(['mobile', 'password', 'activation_code'])) {

            $check_activation_code = User::checkActivationCodeWithoutTime($request->activation_code, '0');
            if ($check_activation_code) {
            $user = User::findUserByMobile($request->mobile);
            if ($user) {

                auth()->loginUsingId($user->id);
                $user = auth()->user();
                Passport::personalAccessTokensExpireIn(Carbon::now()->addWeeks(1));
                $tokenResult = $user->createToken('userToken');
                $tokenModel = $tokenResult->token;
                $tokenModel->save();
                $data = [
                    'user' => new UserResource($user),
                    'token' => $tokenResult->accessToken,
                    'token_type' => 'Bearer'
                ];

                $reference_site = null;
                if (str_contains($request->url(), 'ehyakhak'))
                    $reference_site = User::EHYAKHAK;
                elseif(str_contains($request->url(), 'salamasal'))
                    $reference_site = User::SALAMASAL;

                User::insertUserPassword($user, $request->password, $reference_site);

                return Response::success('به خانه عسل خوش آمدید', $data);

            } else {

                return Response::failed('لطفا از مرحله اول مجددا اقدام به ثبت نام نمایید', null, 422, -3);

            }

            } else
                return Response::failed('لطفا مراحل ثبت نام را به درستی طی نمایید', null, 422, -4);

        }

        // Step two
        if ($request->has(['mobile', 'activation_code'])) {

            $user = User::checkActivationCode($request->activation_code, $request->mobile);

            if ($user)
                return Response::success('کد تایید درست میباشد', null);
            else
                return Response::failed('کد تایید اشتباست لطفا مجددا تلاش نمایید', null, 422, -2);

        }

        // Step one
        if ($request->has(['mobile'])) {

            $user = User::insertUserMobile($request->mobile);

            if ($user) {

                SmsHelper::sendActivationCode($request->mobile);
                return Response::success('کد تایید برای شما ارسال گردید', null);

            } else
                return Response::failed('کسی قبلا با این شماره ثبت نام کرده است', null, 422, -5);

        }


    }

    public function forgotPassword(Request $request)
    {
        $santizier = new Sanitizer($request->all(), [
            'mobile' => 'strip_tags',
            'activation_code' => 'strip_tags',
            'password' => 'strip_tags',
        ]);
        $request_sanitized = $santizier->sanitize();

        $validator = Validator::make($request_sanitized, [
            'mobile' => ['digits:11', 'numeric'],
            'activation_code' => ['digits:5'],
            'password' => [Password::min(8)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            return Response::failed($validator->errors()->toArray(), null, 422, -7);
        }

        if ($request->has(['mobile', 'activation_code', 'password'])) {
            $user = User::checkActivationCode($request->activation_code, $request->mobile);
            if ($user) {
                User::updateNewPassword($request->mobile, $request->password);

                // Send new password to user
                // SmsHelper::sendMessage($request->mobile, $this->templates_id['forgot_password'], $request->password);

                return Response::success(__('به روز رسانی موفق رمز عبور'), null, 200);

            } else {

                return Response::failed(__('به روز رسانی رمز عبور ناموفق بود'), null, 422, -8);

            }

        }

        if ($request->has('mobile', 'activation_code'))
            return Response::failed(__('درخواست ها ناقص است'), null, 422, -9);



        if ($request->has('mobile')) {
            $user = User::checkUserExist($request->mobile);
            if ($user) {
                SmsHelper::sendActivationCode($request->mobile);
                return Response::success(__('ارسال کد فعالسازی و رمز عبور'), null, 200);
            } else
                return Response::failed('متاسفانه شما هنوز ثبت نام نکرده اید', null, 422, -1);

        }


    }

    public function changeMobile(Request $request)
    {
        $santizier = new Sanitizer($request->all(), [
            'mobile' => 'strip_tags',
            'activation_code' => 'strip_tags',
            'new_mobile' => 'strip_tags',
        ]);
        $request_sanitized = $santizier->sanitize();

        $validator = Validator::make($request_sanitized, [
            'mobile' => ['digits:11', 'numeric'],
            'activation_code' => ['digits:5'],
            'new_mobile' => ['digits:11', 'numeric'],
        ]);

        if ($validator->fails())
            return Response::failed($validator->errors()->toArray(), null, 422, -9);


        // Step three
        if ($request->has(['mobile', 'activation_code', 'new_mobile'])) {

            $user = User::checkActivationCodeWithoutTime($request->activation_code, '1');

            if ($user) {

                $mobile = User::updateUserMobile($request->mobile, $request->new_mobile);

                if ($mobile)
                    return Response::failed(__('این شماره موبایل توسط شخصی دیگر استفاده شده'), null, 422, -10);
                else
                    return Response::success(__('شماره موبایل با موفقیت تغییر یافت'), null, 200);

            } else {

                return Response::failed(__('خطایی رخ داده مجددا تلاش نمایید'), null, 422, -10);

            }

        }

        // Step two
        if ($request->has('mobile', 'activation_code')) {

            $user = User::checkActivationCode($request->activation_code, $request->mobile);

            if ($user)
                return Response::success(__('کد تایید درست می باشد'), null, 200);
            else
                return Response::failed(__('کد تایید نادرست می باشد'), null, 422, -11);


        }

        // Step one
        if ($request->has('mobile')) {

            $user = User::checkUserExist($request->mobile);

            if ($user) {

                SmsHelper::sendActivationCode($request->mobile);
                return Response::success(__('ارسال کد فعالسازی'), null, 200);

            } else
                return Response::failed('متاسفانه شما هنوز ثبت نام نکرده اید', null, 422, -1);

        }


    }

}
