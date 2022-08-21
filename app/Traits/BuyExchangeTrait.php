<?php

namespace App\Traits;

use App\Jobs\BuyOrder;
use Lin\Coinex\CoinexExchange;

Trait BuyExchangeTrait{

    public static function calculateTotalTether($pair_amount, $exchange_price = null, $maker_fee_rate, $static_percent)
    {
        $totalTheter = $exchange_price * $pair_amount;
        $wage = ($static_percent / 100) * $totalTheter;
        return $totalTheter + $wage + $maker_fee_rate;
    }

    public static function calculateWage($pair_amount, $exchange_price = null, $static_percent)
    {
        $totalTheter = $exchange_price * $pair_amount;
        return ($static_percent / 100) * $totalTheter;
    }

    public static function calculateTomanAmount($total_tether, $tether_price)
    {
        return $tether_price * $total_tether;
    }

    public static function coinexBuyOrder($coinex_order_info, $type, $lang)
    {
        if ($type == 'market') {
            $key = '420507A8F0DC40249423722D4EF81ED1';
            $secret = 'A3CE29DEB945AA91743A2F6F5805F1F9574D992ABA72731F';
            $coinex = new CoinexExchange($key, $secret);

            $data = [
                'access_id' => $coinex_order_info['access_id'],
                'tonce' => $coinex_order_info['tonce'],
                'market' => $coinex_order_info['market'],
                'type' => $coinex_order_info['type'],
                'amount' => $coinex_order_info['amount'],
                'client_id' => $coinex_order_info['client_id'],

            ];


            $result = $coinex->trading()->postMarket($data);

            // Try 3 times
            $i = 0;
            while ($result['code'] != 0) {
                if ($i < 3)
                    break;
                $result = $coinex->trading()->postMarket($data);
                $i++;
            }

            if ($result['code'] != 0)
                return self::coinexErrorDetection($result['code'], $lang);
            elseif ($result['code'] == 0)
                return $result['data'];


        } else {
            BuyOrder::dispatch($coinex_order_info, $type)->onQueue('buyOrder');
            return 0;
        }

    }

    public static function coinexErrorDetection($error_code, $lang): array
    {
        switch ($error_code) {
            case 1:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'خطا';
                        break;
                    case 'en':
                        $error = 'Error';
                        break;
                }
                break;
            case 2:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'آرکومان ها نا معتبر هستن';
                        break;
                    case 'en':
                        $error = 'Invalid argument';
                        break;
                }
                break;
            case 3:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'خطای داخلی';
                        break;
                    case 'en':
                        $error = 'Internal error';
                        break;
                }
                break;
            case 23:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'آدرس IP ممنوع می باشد';
                        break;
                    case 'en':
                        $error = 'IP prohibited';
                        break;
                }
                break;
            case 24:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'موجود نمی باشد' . ' AcessesID';
                        break;
                    case 'en':
                        $error = 'AccessID does not exist';
                }
                break;
            case 25:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'Signature ' . 'خطای';
                        break;
                    case 'en':
                        $error = 'Signature error';
                }
                break;
            case 34:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'منقضی شده است';
                        break;
                    case 'en':
                        $error = 'AccessID expired';
                        break;
                }
                break;
            case 35:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'سرویس در دسترس نیست';
                        break;
                    case 'en':
                        $error = 'Service unavailable';
                        break;
                }
                break;
            case 36:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'پایان سرویس';
                        break;
                    case 'en':
                        $error = 'Service timeout';
                        break;
                }
                break;
            case 40:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'حساب اصلی و حساب فرعی مطابقت ندارند';
                        break;
                    case 'en':
                        $error = 'Main account and sub-account do not match';
                        break;
                }
                break;
            case 49:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'انتقال به حساب فرعی رد شد';
                        break;
                    case 'en':
                        $error = 'The transfer to the sub-account was rejected';
                        break;
                }
                break;
            case 107:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'موجودی ناکافی است';
                        break;
                    case 'en':
                        $error = 'Insufficient balance';
                        break;
                }
                break;
            case 158:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'دسترسی برای استفاده ازین API نیست';
                        break;
                    case 'en':
                        $error = 'No permission to use this API';
                        break;
                }
                break;
            case 213:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'درخواست‌ها خیلی مکرر ارسال می‌شوند';
                        break;
                    case 'en':
                        $error = 'Requests submitted too frequently';
                        break;
                }
                break;
            case 227:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'اشتباه است، timestamp باید در بازه زمانی 60± سرور باشد' . ' timestamp';
                        break;
                    case 'en':
                        $error = 'The timestamp is wrong, the timestamp must be within ±60s of the server time';
                        break;
                }
                break;
            case 600:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'عدد سفارش موجود نیست';
                        break;
                    case 'en':
                        $error = 'Order number does not exist';
                        break;
                }
                break;
            case 601:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'سفارشات سایر کاربران';
                        break;
                    case 'en':
                        $error = 'Other users’ orders';
                        break;
                }
                break;
            case 602:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'زیر حداقل حد خرید یا فروش';
                        break;
                    case 'en':
                        $error = 'Below the minimum buying or selling limit';
                        break;
                }
                break;
            case 606:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'قیمت سفارش خیلی از آخرین قیمت معامله انحراف دارد';
                        break;
                    case 'en':
                        $error = 'The order price deviates too much from the latest transaction price';
                        break;
                }
                break;
            case 651:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'خطای عمق ادغام';
                        break;
                    case 'en':
                        $error = 'Merge depth error';
                        break;
                }
                break;
            case 3008:
                switch ($lang) {
                    case 'fa':
                    default:
                        $error = 'سرویس مشغول است، لطفاً بعداً دوباره امتحان کنید';
                        break;
                    case 'en':
                        $error = 'Service busy, please try again later.';
                        break;
                }
                break;
        }
        return [
            'code' => $error_code,
            'error' => $error,
        ];
    }
}
