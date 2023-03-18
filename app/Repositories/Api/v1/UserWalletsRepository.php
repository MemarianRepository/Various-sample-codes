<?php

namespace App\Repositories\Api\v1;

use App\Models\Api\v1\ExchangeList;
use App\Models\Api\v1\UserWallets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserWalletsRepository
{
    public static function getUserWallet($user_id, $wallet_name)
    {
        $user_wallet = UserWallets::query()
                            ->where([
                                'user_id' => $user_id,
                                'wallet' => $wallet_name
                            ])->first();
        if(!$user_wallet){
            $exchange = ExchangeList::query()->where('symbol', $wallet_name)->first(['id']);
            $user_wallet = new UserWallets;
            $user_wallet->user_id = $user_id;
            $user_wallet->exchange_id = @$exchange->id;
            $user_wallet->amount = 0;
            $user_wallet->wallet = $wallet_name;
            $user_wallet->status = 1;
            $user_wallet->save();
            return $user_wallet;
        }
        else
        {
            return $user_wallet;
        }
    }

    public static function chargeTomanWallet($amount, $user_id = null)
    {
        if ($user_id) {

            $user = UserWallets::query()
                ->where([
                    'user_id' => $user_id,
                    'wallet' => 'IRR'
                ])->first();

        } else {

            $user = UserWallets::query()
                ->where([
                    'user_id' => Auth::guard('api')->id(),
                    'wallet' => 'IRR'
                ])->first();

        }


        $user->amount += $amount;
        $user->save();
    }

    public static function chargeTomanWalletWithAuth($amount)
    {
        $user = UserWallets::query()
            ->where([
                'user_id' => Auth::id(),
                'wallet' => 'IRR'
            ])->first();

        $user->amount += $amount;
        $user->save();
    }

    public static function increaseWalletAmount($user_id, $wallet_name, $amount)
    {
        $user_wallet = UserWallets::query()
            ->where([
                'user_id' => $user_id,
                'wallet' => $wallet_name,
            ])->first();
        if(!$user_wallet){
            $exchange = ExchangeList::query()->where('symbol', $wallet_name)->first(['id']);
            $user_wallet = new UserWallets;
            $user_wallet->user_id = $user_id;
            $user_wallet->exchange_id = $exchange->id;
            $user_wallet->amount = $amount;
            $user_wallet->wallet = $wallet_name;
            $user_wallet->status = 1;
            $user_wallet->save();
            return;
        }
        $user_wallet->amount += $amount;
        $user_wallet->save();
    }

    public static function decreaseWalletAmount($user_id, $wallet_name, $amount)
    {
        $user_wallet = UserWallets::query()
            ->where([
                'user_id' => $user_id,
                'wallet' => $wallet_name,
            ])->first();
        $user_wallet->amount = abs($amount - $user_wallet->amount);
        $user_wallet->save();
    }

    public static function chargeUserWalletAfterDeposit($user_id, $deposit_amount, $wallet_name)
    {
        $wallet = UserWallets::query()->where([
            'user_id' => $user_id,
            'wallet' => $wallet_name,
        ])->first();
        $wallet->amount += $deposit_amount;
        $wallet->save();
    }

}
