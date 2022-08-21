<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\SmsHelper;
use App\Http\Controllers\Controller;
use App\Models\Api\v1\BinanceCurrency;
use App\Models\Api\v1\CoinexMarketInfoExtracted;
use App\Models\Api\v1\DoneOrdersList;
use App\Models\Api\v1\ExchangeSetting;
use App\Models\Api\v1\KucoinCurrency;
use App\Models\Api\v1\OrdersList;
use App\Models\Api\v1\OrdersLog;
use App\Models\Currency;
use App\Models\OrderSetting;
use App\Repositories\Api\v1\OrderRepository;
use App\Repositories\Api\v1\UserWalletsRepository;
use Illuminate\Http\Request;
use App\Traits\SellExchangeTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class SellExchangeController extends Controller
{
    use SellExchangeTrait;

    private $userWalletsRepository;
    private $orderRepository;

    public function __construct(UserWalletsRepository $userWalletsRepository, OrderRepository $orderRepository)
    {
        $this->userWalletsRepository = $userWalletsRepository;
        $this->orderRepository = $orderRepository;
    }

    public function sellExchange(Request $request)
    {
        $tetherPrice = 30000;
        $static_percent = 0.3;

        if ($request->type == 'market') {
//          Market invoice or sell tether
            if ($request->pair_type == 'USDT' || $request->pair_type == 'usdt') {
                $total_price = $request->pair_amount * $tetherPrice;
                $data = [
                    'your_payment' => $request->pair_amount,
                    'wage_percent' => 0,
                    'your_receipt' => $total_price,
                ];
                if($request->request_type == 'invoice') {
                    switch ($request->lang)
                    {
                        case 'fa':
                        default:
                            return Response::success('مقدار تومان محاسبه گردید', $data, 200);
                        case 'en':
                            return Response::success('Toman amount calculated', $data, 200);
                    }
                }
                elseif($request->request_type == 'sell'){
                    $user_wallet = $this->userWalletsRepository
                        ->getUserWallet(Auth::guard('api')->id(), 'USDT');

                    if($user_wallet->amount < $request->pair_amount){
                        $required_tether_amount  =  $request->pair_amount - $user_wallet->amount;
                        $data = [
                            'required_tether_amount' => $required_tether_amount
                        ];
                        switch ($request->lang)
                        {
                            case 'fa':
                            default:
                                return Response::failed('متاسفم، مقدار تتر بیشتری مورد نیاز است', $data, 422, -1);
                            case 'en':
                                return Response::failed('sorry, more tether are needed', $data, 422, -1);
                        }

                    }
                    else{
                        $this->userWalletsRepository
                            ->decreaseWalletAmount(Auth::guard('api')->id(), 'USDT', $request->pair_amount);
                        $this->userWalletsRepository
                            ->increaseWalletAmount(Auth::guard('api')->id(), 'IRR', $total_price);

                        switch ($request->lang)
                        {
                            case 'fa':
                            default:
                                return Response::success('تبریک، ولت تومانی شما شارژ شد', null, 200);
                            case 'en':
                                return Response::success('congratulation, your toman wallet has been charged', null, 200);
                        }

                    }
                }
            }
//          End market invoice or sell tether

//          Market invoice or sell exchanges
            $exchange_setting = ExchangeSetting::getExchangeSettings();
            switch ($exchange_setting->exchange) {
                case 'کوینکس':
                default:
                    // Get coinex market info for find sepcific currency and min amount and taker fee rate
                    $coinex_market_info = CoinexMarketInfoExtracted::findMarketInfo(strtoupper($request->pair_type) . 'USDT');
                    $coinex_market_info = json_decode($coinex_market_info->data);
                    $min_amount = $coinex_market_info->min_amount;
                    $maker_fee_rate = $coinex_market_info->maker_fee_rate;

                    // Find pair type in Currency model
                    $currencies = Currency::getLastInfo();
                    $data = json_decode($currencies->data);
                    $invoice = [];
                    foreach ($data->data->ticker as $exchange => $info) {
                        if ($exchange == strtoupper($request->pair_type) . 'USDT') {
                            $wage = SellExchangeTrait::calculateWage($request->pair_amount, $info->last, $static_percent);
                            $total_tether = SellExchangeTrait::calculateTotalTether($request->pair_amount, $info->last, $maker_fee_rate, $static_percent);
                            $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);
                            switch ($request->receipt_type) {
                                case 'usdt':
                                default:
                                    $invoice = [
                                        'your_payment' => $request->pair_amount,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $total_tether,
                                        'currency_type' => 'USDT'
                                    ];
                                    break;
                                case 'toman':
                                    $invoice = [
                                        'your_payment' => $request->pair_amount,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $toman_amount,
                                        'currency_type' => 'IRR'
                                    ];
                                    break;
                            }
                        }
                    }
                    if ($request->request_type == 'invoice') {

                        switch ($request->lang) {
                            case 'fa':
                            default:
                                return Response::success('ارز مورد نظر محاسبه گردید', $invoice, 200);
                            case 'en':
                                return Response::success('Your exchange info calculated', $invoice, 200);
                        }

                    } elseif ($request->request_type == 'sell') {

                        // Check user amount with minimum amount of order
                        if ($request->pair_amount < $min_amount) {
                            $data = [
                                'min_required_pair_amount' => $min_amount,
                                'your_pair_amount' => $request->pair_amount,
                                'difference_in_min_amount' => $min_amount - $request->pair_amount
                            ];
                            switch ($request->lang) {
                                case 'fa':
                                default:
                                    return Response::failed('حداقل سفارش در ارز مورد نظر بیشتر می باشد', $data, 200, -4);
                                case 'en':
                                    return Response::failed('The minimum order is higher in the order currency', $data, 200, -4);
                            }
                        }

                        // Get user wallet (that's have currency)
                        $user_wallet = $this->userWalletsRepository
                            ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));

                        // Check amount of wallet
                        if ($user_wallet->amount < $request->pair_amount) {

                            $required_exchange_amount = $request->pair_amount - $user_wallet->amount;
                            $data = [
                                'required_exchange_amount' => $required_exchange_amount
                            ];
                            switch ($request->lang) {
                                case 'fa':
                                default:
                                    return Response::failed('متاسفم، مقدار ارز بیشتری مورد نیاز است', $data, 422, -2);
                                case 'en':
                                    return Response::failed('sorry, more ' . strtolower($request->pair_type) . ' are needed', $data, 422, -2);
                            }

                        } else {

                            $this->userWalletsRepository
                                ->decreaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);

                            // Define receipt type
                            switch ($request->receipt_type) {
                                case 'usdt':
                                    $this->userWalletsRepository
                                        ->increaseWalletAmount(Auth::guard('api')->id(), 'USDT', $total_tether);
                                    break;
                                case 'toman':
                                default:
                                    $this->userWalletsRepository
                                        ->increaseWalletAmount(Auth::guard('api')->id(), 'IRR', $toman_amount);
                                    break;
                            }

                            $order_setting = OrderSetting::checkOrderMode();
                            $automatic_mode = 'اتوماتیک';
                            $coinex_order_result = 0;
                            if ($order_setting->mode == $automatic_mode) {

                                // Prepare send request to coinex
                                $coinex_order_info = [
                                    'access_id' => '1',
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => 'buy',
                                    'amount' => $request->pair_amount,
                                    'tonce' => now()->toDateTimeString(),
                                    'account_id' => 0,
                                    'client_id' => Auth::guard('api')->user()->mobile
                                ];

                                // Send request to coinex
                                $coinex_order_result = SellExchangeTrait::SellOrderCoinex($coinex_order_info, $request->type, @$request->lang);

                            } else {
                                
                                $order_info = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'time' => now(),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => OrdersList::MARKET_TYPE,
                                    'role' => OrdersList::SELL_ROLE,
                                    'order_price' => $toman_amount,
                                    'avg_price' => 0,
                                    'amount' => $request->pair_amount,
                                    'total_price' => $total_tether,
                                    'current_wage' => $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::PENDING_STATUS,
                                ];
                                OrderRepository::storeSellOrders($order_info);
                            }

                            if ($coinex_order_result == 0) {
                                // Store real order

                                // Store order done
                                $order_info = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'time' => now(),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => OrdersList::MARKET_TYPE,
                                    'role' => OrdersList::SELL_ROLE,
                                    'order_price' => $toman_amount,
                                    'avg_price' => 0,
                                    'amount' => $request->pair_amount,
                                    'total_price' => $total_tether,
                                    'current_wage' => $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::DONE_STATUS,
                                ];
                                OrderRepository::storeSellOrders($order_info);

                                // Send message of user order
                                SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['sell_market_order'], $request->pair_amount);

                                switch ($request->lang) {
                                    case 'fa':
                                    default:
                                        return Response::success('تبریک، ولت شما با موفقیت شارژ شد', null, 200);
                                    case 'en':
                                        return Response::success('congratulation, your ' . strtolower($request->pair_type) . ' wallet has been charged', null, 200);
                                }

                            } else {

                                $order_log = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => OrdersList::MARKET_TYPE,
                                    'role' => OrdersList::SELL_ROLE,
                                    'order_price' => $toman_amount,
                                    'avg_price' => 0,
                                    'amount' => $request->pair_amount,
                                    'total_price' => $total_tether,
                                    'current_wage' => $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::CANCEL_STATUS,
                                    'exchange_error' => $coinex_order_result['error']
                                ];

                                OrdersLog::storeLog($order_log);

                                return Response::failed($coinex_order_result['error'], null, 400, '-'.$coinex_order_result['code']);
                            }


                        }

                    }


                    break;

                case 'کوکوین':
                    $market_info = KucoinCurrency::getLastInfo();
                    $data = json_decode($market_info->data);
                    foreach ($data->data->ticker as $market_info) {
                        if ($market_info->symbol == strtoupper($request->pair_type) . '-USDT') {
                            $maker_fee_rate = $market_info->makerFeeRate;
                            $invoice = [];
                            $wage = SellExchangeTrait::calculateWage($request->pair_amount, $market_info->last, $static_percent);
                            $total_tether = SellExchangeTrait::calculateTotalTether($request->pair_amount, $market_info->last, $maker_fee_rate, $static_percent);
                            $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);
                            switch ($request->receipt_type) {
                                case 'usdt':
                                default:
                                    $invoice = [
                                        'your_payment' => $request->pair_amount,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $total_tether,
                                        'currency_type' => 'USDT'
                                    ];
                                    break;
                                case 'toman':
                                    $invoice = [
                                        'your_payment' => $request->pair_amount,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $toman_amount,
                                        'currency_type' => 'IRR'
                                    ];
                                    break;
                            }

                            if ($request->request_type == 'invoice') {
                                switch ($request->lang) {
                                    case 'fa':
                                    default:
                                        return Response::success('ارز مورد نظر محاسبه گردید', $invoice, 200);
                                    case 'en':
                                        return Response::success('Your exchange info calculated', $invoice, 200);
                                }
                            } elseif ($request->request_type == 'sell') {

                                $user_wallet = $this->userWalletsRepository
                                    ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));

                                if ($user_wallet->amount < $request->pair_amount) {
                                    $required_exchange_amount = $request->pair_amount - $user_wallet->amount;
                                    $data = [
                                        'required_exchange_amount' => $required_exchange_amount
                                    ];

                                    switch ($request->lang) {
                                        case 'fa':
                                        default:
                                            return Response::failed('متاسفم، مقدار ارز بیشتری مورد نیاز است', $data, 422, -2);
                                        case 'en':
                                            return Response::failed('sorry, more ' . strtolower($request->pair_type) . ' are needed', $data, 422, -2);
                                    }

                                } else {
                                    $this->userWalletsRepository
                                        ->decreaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);

                                    switch ($request->receipt_type) {
                                        case 'usdt':
                                            $this->userWalletsRepository
                                                ->increaseWalletAmount(Auth::guard('api')->id(), 'USDT', $total_tether);
                                            break;
                                        case 'toman':
                                        default:
                                            $this->userWalletsRepository
                                                ->increaseWalletAmount(Auth::guard('api')->id(), 'IRR', $toman_amount);
                                            break;
                                    }


                                    $order_setting = OrderSetting::checkOrderMode();
                                    $coinex_order_result = 0;
                                    if ($order_setting->mode == 'اتوماتیک') {
                                        $coinex_order_info = [
                                            'access_id' => '1',
                                            'market' => strtoupper($request->pair_type) . 'USDT',
                                            'type' => 'sell',
                                            'amount' => $request->pair_amount,
                                            'tonce' => now()->toDateTimeString(),
                                            'account_id' => 0,
                                            'client_id' => Auth::guard('api')->user()->mobile
                                        ];

                                        $coinex_order_result = SellExchangeTrait::SellOrderCoinex($coinex_order_info, $request->type, @$request->lang);
                                    }

                                    if ($coinex_order_result == 0) {

                                        $order_info = [
                                            'user_id' => Auth::guard('api')->id(),
                                            'time' => now(),
                                            'market' => strtoupper($request->pair_type) . 'USDT',
                                            'type' => OrdersList::MARKET_TYPE,
                                            'role' => OrdersList::SELL_ROLE,
                                            'order_price' => $toman_amount,
                                            'avg_price' => 0,
                                            'amount' => $request->pair_amount,
                                            'total_price' => $total_tether,
                                            'current_wage' => $wage,
                                            'filled' => 0,
                                            'status' => OrdersList::DONE_STATUS,
                                        ];

                                        $order_info = OrderRepository::storeBuyOrders($order_info);


                                        // Prepare coinex done order information (order_id is foreign key on orders_list table)
                                        $done_order_info = [
                                            'order_id' => $order_info->id,
                                            'amount' => @$coinex_order_result['amount'],
                                            'asset_fee' => @$coinex_order_result['asset_fee'],
                                            'avg_price' => @$coinex_order_result['avg_price'],
                                            'client_id' => @$coinex_order_result['client_id'],
                                            'create_time' => @$coinex_order_result['create_time'],
                                            'deal_amount' => @$coinex_order_result['deal_amount'],
                                            'deal_fee' => @$coinex_order_result['deal_fee'],
                                            'deal_money' => @$coinex_order_result['deal_money'],
                                            'fee_asset' => @$coinex_order_result['fee_asset'],
                                            'fee_discount' => @$coinex_order_result['fee_discount'],
                                            'finished_time' => @$coinex_order_result['finished_time'],
                                            'id' => @$coinex_order_result['id'],
                                            'left' => @$coinex_order_result['left'],
                                            'maker_fee_rate' => @$coinex_order_result['maker_fee_rate'],
                                            'market' => @$coinex_order_result['market'],
                                            'money_fee' => @$coinex_order_result['money_fee'],
                                            'order_type' => @$coinex_order_result['order_type'],
                                            'price' => @$coinex_order_result['price'],
                                            'status' => @$coinex_order_result['status'],
                                            'stock_fee' => @$coinex_order_result['stock_fee'],
                                            'taker_fee_rate' => @$coinex_order_result['taker_fee_rate'],
                                            'type' => @$coinex_order_result['type']
                                        ];

                                        // Store done order
                                        DoneOrdersList::store($done_order_info);

                                        // Send message of user order
                                        SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['market_order'], $request->pair_amount);

                                        switch ($request->lang) {
                                            case 'fa':
                                            default:
                                                return Response::success('تبریک، ولت شما با موفقیت شارژ شد', null, 200);
                                            case 'en':
                                                return Response::success('congratulation, your ' . strtolower($request->pair_type) . ' wallet has been charged', null, 200);
                                        }
                                    } else {

                                        $order_log = [
                                            'user_id' => Auth::guard('api')->id(),
                                            'market' => strtoupper($request->pair_type) . 'USDT',
                                            'type' => OrdersList::MARKET_TYPE,
                                            'role' => OrdersList::BUY_ROLE,
                                            'order_price' => $total_tether,
                                            'avg_price' => 0,
                                            'amount' => $request->pair_amount,
                                            'total_price' => $toman_amount,
                                            'current_wage' => $wage,
                                            'filled' => 0,
                                            'status' => OrdersList::CANCEL_STATUS,
                                            'exchange_error' => $coinex_order_result['error']
                                        ];

                                        OrdersLog::storeLog($order_log);

                                        return Response::failed($coinex_order_result['error'], null, 400, '-'.$coinex_order_result['code']);
                                    }
                                    // End user buy currenct with usdt


                                }
                            }
                        }
                    }
                    break;

                case 'بایننس':
                    $market_info = BinanceCurrency::getLastInfo();
                    $data = json_decode($market_info->data);
                    foreach ($data as $market_info) {
                        if ($market_info->symbol == strtoupper($request->pair_type) . 'USDT') {
                            $maker_fee_rate = ($request->pair_amount * $market_info->price) * 0.1;
                            $invoice = [];
                            $wage = SellExchangeTrait::calculateWage($request->pair_amount, $market_info->price, $static_percent);
                            $total_tether = SellExchangeTrait::calculateTotalTether($request->pair_amount, $market_info->price, $maker_fee_rate, $static_percent);
                            $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);
                            switch ($request->receipt_type) {
                                case 'usdt':
                                default:
                                    $invoice = [
                                        'your_payment' => $request->pair_amount,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $total_tether,
                                        'currency_type' => 'USDT'
                                    ];
                                    break;
                                case 'toman':
                                    $invoice = [
                                        'your_payment' => $request->pair_amount,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $toman_amount,
                                        'currency_type' => 'IRR'
                                    ];
                                    break;
                            }

                            if ($request->request_type == 'invoice') {
                                switch ($request->lang) {
                                    case 'fa':
                                    default:
                                        return Response::success('ارز مورد نظر محاسبه گردید', $invoice, 200);
                                    case 'en':
                                        return Response::success('Your exchange info calculated', $invoice, 200);
                                }
                            } elseif ($request->request_type == 'sell') {
                                $user_wallet = $this->userWalletsRepository
                                    ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));
                                if ($user_wallet->amount < $request->pair_amount) {
                                    $required_exchange_amount = $request->pair_amount - $user_wallet->amount;
                                    $data = [
                                        'required_exchange_amount' => $required_exchange_amount
                                    ];
                                    switch ($request->lang) {
                                        case 'fa':
                                        default:
                                            return Response::failed('متاسفم، مقدار ارز بیشتری مورد نیاز است', $data, 422, -2);
                                        case 'en':
                                            return Response::failed('sorry, more ' . strtolower($request->pair_type) . ' are needed', $data, 422, -2);
                                    }
                                } else {
                                    $this->userWalletsRepository
                                        ->decreaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);

                                    switch ($request->receipt_type) {
                                        case 'usdt':
                                            $this->userWalletsRepository
                                                ->increaseWalletAmount(Auth::guard('api')->id(), 'USDT', $total_tether);
                                            break;
                                        case 'toman':
                                        default:
                                            $this->userWalletsRepository
                                                ->increaseWalletAmount(Auth::guard('api')->id(), 'IRR', $toman_amount);
                                            break;
                                    }

                                    $order_info = [
                                        'user_id' => Auth::guard('api')->id(),
                                        'time' => now(),
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => OrdersList::MARKET_TYPE,
                                        'role' => OrdersList::SELL_ROLE,
                                        'order_price' => $toman_amount,
                                        'avg_price' => 0,
                                        'amount' => $request->pair_amount,
                                        'total_price' => $total_tether,
                                        'current_wage' => $wage,
                                        'filled' => 0,
                                        'status' => OrdersList::PENDING_STATUS,
                                    ];

                                    OrderRepository::storeBuyOrders($order_info);

                                    $order_setting = OrderSetting::checkOrderMode();
                                    if ($order_setting->mode == 'اتوماتیک') {
                                        $coinex_order_info = [
                                            'access_id' => '1',
                                            'market' => strtoupper($request->pair_type) . 'USDT',
                                            'type' => 'buy',
                                            'amount' => $request->pair_amount,
                                            'tonce' => now()->toDateTimeString(),
                                            'account_id' => 0,
                                            'client_id' => Auth::guard('api')->user()->mobile
                                        ];

                                        SellExchangeTrait::SellOrderCoinex($coinex_order_info, $request->type, @$request->lang);
                                    }

                                    switch ($request->lang) {
                                        case 'fa':
                                        default:
                                            return Response::success('تبریک، ولت شما با موفقیت شارژ شد', null, 200);
                                        case 'en':
                                            return Response::success('congratulation, your ' . strtolower($request->receipt_type) . ' wallet has been charged', null, 200);
                                    }

                                }
                            }
                        }
                    }
                    break;

            }
//          end market invoice or sell exchanges
        }

        if ($request->type == 'limit' && $request->has('pair_price')){
//          Limit invoice or buy tether
            if ($request->pair_type == 'USDT' || $request->pair_type == 'usdt') {
                $total_price = $request->pair_amount * $request->pair_price;
                $data = [
                    'your_payment' => $request->pair_amount,
                    'wage_percent' => 0,
                    'your_receipt' => $total_price
                ];
                if($request->request_type == 'invoice') {
                    switch ($request->lang)
                    {
                        case 'fa':
                        default:
                            return Response::success('مقدار تتر محاسبه گردید', $data, 200);
                        case 'en':
                            return Response::success('Tether amount calculated', $data, 200);
                    }
                }
                elseif($request->request_type == 'sell'){
                    $user_wallet = $this->userWalletsRepository
                        ->getUserWallet(Auth::guard('api')->id(), 'USDT');

                    if($user_wallet->amount < $request->pair_amount){
                        $required_tether_amount  =  $request->pair_amount - $user_wallet->amount;
                        $data = [
                            'required_tether_amount' => $required_tether_amount
                        ];
                        switch ($request->lang)
                        {
                            case 'fa':
                            default:
                                return Response::failed('متاسفم، مقدار تتری بیشتری مورد نیاز است', $data, 422, -1);
                            case 'en':
                                return Response::failed('sorry, more tether are needed', $data, 422, -1);
                        }

                    }
                    else{
                        $this->userWalletsRepository
                            ->decreaseWalletAmount(Auth::guard('api')->id(), 'USDT', $request->pair_amount);
                        $this->userWalletsRepository
                            ->increaseWalletAmount(Auth::guard('api')->id(), 'IRR', $total_price);

                        $order_info = [
                            'user_id' => Auth::guard('api')->id(),
                            'time' => now(),
                            'market' => 'USDT',
                            'type' => OrdersList::LIMIT_TYPE,
                            'role' => OrdersList::SELL_ROLE,
                            'order_price' => $total_price,
                            'avg_price' => 0,
                            'amount' => $request->pair_amount,
                            'total_price' => $total_price,
                            'current_wage' => 0,
                            'filled' => 0,
                            'status' => OrdersList::PENDING_STATUS,
                        ];

                        OrderRepository::storeBuyOrders($order_info);

                        $order_setting = OrderSetting::checkOrderMode();
                        if($order_setting->mode == 'اتوماتیک')
                        {
                            $coinex_order_info = [
                                'access_id' => '1',
                                'market' => strtoupper($request->pair_type) . 'USDT',
                                'type' => 'sell',
                                'amount' => $request->pair_amount,
                                'tonce' => now()->toDateTimeString(),
                                'account_id' => 0,
                                'client_id' => Auth::guard('api')->user()->mobile
                            ];

                            SellExchangeTrait::SellOrderCoinex($coinex_order_info, $request->type, @$request->lang);
                        }
                        switch ($request->lang)
                        {
                            case 'fa':
                            default:
                                return Response::success('تبریک، ولت  تتری شما با موفقیت شارژ شد', null, 200);
                            case 'en':
                                return Response::success('congratulation, your tether wallet has been charged', null, 200);
                        }
                    }

                }
            }
//          end limit invoice or buy tether

//          Limit invoice or sell exchanges
            $exchange_setting = ExchangeSetting::getExchangeSettings();
            switch ($exchange_setting->exchange) {
                case 'کوینکس':
                default:
                    // Get coinex market info for find sepcific currency and min amount and taker fee rate
                    $coinex_market_info = CoinexMarketInfoExtracted::findMarketInfo(strtoupper($request->pair_type) . 'USDT');
                    $coinex_market_info = json_decode($coinex_market_info->data);
                    $maker_fee_rate = $coinex_market_info->maker_fee_rate;

                    $invoice = [];
                    $wage = SellExchangeTrait::calculateWage($request->pair_amount, $request->pair_price, $static_percent);
                    $total_tether = SellExchangeTrait::calculateTotalTether($request->pair_amount, $request->pair_price, $maker_fee_rate, $static_percent);
                    $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);
                    switch ($request->receipt_type)
                    {
                        case 'usdt':
                        default:
                            $invoice =  [
                                'your_payment' => $request->pair_amount,
                                'wage_percent' => $wage,
                                'your_receipt' => $total_tether,
                                'currency_type' => 'USDT'
                            ];
                            break;
                        case 'toman':
                            $invoice =  [
                                'your_payment' => $request->pair_amount,
                                'wage_percent' => $wage,
                                'your_receipt' => $toman_amount,
                                'currency_type' => 'IRR'
                            ];
                            break;
                    }

                    if($request->request_type == 'invoice') {
                        switch ($request->lang) {
                            case 'fa':
                            default:
                                return Response::success('ارز مورد نظر محاسبه گردید', $invoice, 200);
                            case 'en':
                                return Response::success('Your exchange info calculated', $invoice, 200);
                        }
                    }
                    elseif($request->request_type == 'sell'){
                        $user_wallet = $this->userWalletsRepository
                            ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));
                        if($user_wallet->amount < $request->pair_amount)
                        {
                            $required_exchange_amount  =  $request->pair_amount - $user_wallet->amount;
                            $data = [
                                'required_'.strtolower($request->pair_type).'_amount' => $required_exchange_amount
                            ];
                            switch ($request->lang)
                            {
                                case 'fa':
                                default:
                                    return Response::failed('متاسفم، مقدار ارز بیشتری مورد نیاز است', $data, 422, -2);
                                case 'en':
                                    return Response::failed('sorry, more '.strtolower($request->pair_type).' are needed', $data, 422, -2);
                            }
                        }
                        else{
                            $this->userWalletsRepository
                                ->decreaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);
                            switch ($request->receipt_type)
                            {
                                case 'usdt':
                                    $this->userWalletsRepository
                                        ->increaseWalletAmount(Auth::guard('api')->id(), 'USDT', $total_tether);
                                    break;
                                case 'toman':
                                default:
                                    $this->userWalletsRepository
                                        ->increaseWalletAmount(Auth::guard('api')->id(), 'IRR', $toman_amount);
                                    break;
                            }

                            $order_info = [
                                'user_id' => Auth::guard('api')->id(),
                                'time' => now(),
                                'market' => strtoupper($request->pair_type).'USDT',
                                'type' => OrdersList::LIMIT_TYPE,
                                'role' => OrdersList::SELL_ROLE,
                                'order_price' => $total_tether,
                                'avg_price' => 0,
                                'amount' => $request->pair_amount,
                                'total_price' => $toman_amount,
                                'current_wage' => $wage,
                                'filled' => 0,
                                'status' => OrdersList::PENDING_STATUS,
                            ];

                            OrderRepository::storeBuyOrders($order_info);

                            $order_setting = OrderSetting::checkOrderMode();
                            if($order_setting->mode == 'اتوماتیک')
                            {
                                $coinex_order_info = [
                                    'access_id' => '1',
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => 'sell',
                                    'amount' => $request->pair_amount,
                                    'tonce' => now()->toDateTimeString(),
                                    'account_id' => 0,
                                    'client_id' => Auth::guard('api')->user()->mobile
                                ];

                                SellExchangeTrait::SellOrderCoinex($coinex_order_info, $request->type, @$request->lang);
                            }

                            switch ($request->lang) {
                                case 'fa':
                                default:
                                    return Response::success('تبریک، سفارش شما با موفقیت ثبت شد', null, 200);
                                case 'en':
                                    return Response::success('congratulation, your order has been registered', null, 200);
                            }
                        }

                    }

                    break;

                case 'کوکوین':
                    $market_info = KucoinCurrency::getLastInfo();
                    $data = json_decode($market_info->data);
                    foreach ($data->data->ticker as $market_info) {
                        if ($market_info->symbol == strtoupper($request->pair_type) . '-USDT') {
                            $maker_fee_rate = $market_info->makerFeeRate;
                            $invoice = [];
                            $wage = SellExchangeTrait::calculateWage($request->pair_amount, $request->pair_price, $static_percent);
                            $total_tether = SellExchangeTrait::calculateTotalTether($request->pair_amount, $request->pair_price, $maker_fee_rate, $static_percent);
                            $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);
                            switch ($request->receipt_type)
                            {
                                case 'usdt':
                                default:
                                    $invoice =  [
                                        'your_payment' => $request->pair_amount,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $total_tether,
                                        'currency_type' => 'USDT'
                                    ];
                                    break;
                                case 'toman':
                                    $invoice =  [
                                        'your_payment' => $request->pair_amount,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $toman_amount,
                                        'currency_type' => 'IRR'
                                    ];
                                    break;
                            }

                            if($request->request_type == 'invoice') {
                                switch ($request->lang)
                                {
                                    case 'fa':
                                    default:
                                        return Response::success('ارز مورد نظر محاسبه گردید', $invoice, 200);
                                    case 'en':
                                        return Response::success('Your exchange info calculated', $invoice, 200);
                                }
                            }
                            elseif($request->request_type == 'sell'){
                                $user_wallet = $this->userWalletsRepository
                                    ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));
                                if($user_wallet->amount < $request->pair_amount)
                                {
                                    $required_exchange_amount  =  $request->pair_amount - $user_wallet->amount;
                                    $data = [
                                        'required_'.strtolower($request->pair_type).'_amount' => $required_exchange_amount
                                    ];
                                    switch ($request->lang)
                                    {
                                        case 'fa':
                                        default:
                                            return Response::failed('متاسفم، مقدار ارز بیشتری مورد نیاز است', $data, 422, -2);
                                        case 'en':
                                            return Response::failed('sorry, more '.strtolower($request->pair_type).' are needed', $data, 422, -2);
                                    }
                                }
                                else{
                                    $this->userWalletsRepository
                                        ->decreaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);
                                    switch ($request->receipt_type)
                                    {
                                        case 'usdt':
                                            $this->userWalletsRepository
                                                ->increaseWalletAmount(Auth::guard('api')->id(), 'USDT', $total_tether);
                                            break;
                                        case 'toman':
                                        default:
                                            $this->userWalletsRepository
                                                ->increaseWalletAmount(Auth::guard('api')->id(), 'IRR', $toman_amount);
                                            break;
                                    }

                                    $order_info = [
                                        'user_id' => Auth::guard('api')->id(),
                                        'time' => now(),
                                        'market' => strtoupper($request->pair_type).'USDT',
                                        'type' => OrdersList::LIMIT_TYPE,
                                        'role' => OrdersList::SELL_ROLE,
                                        'order_price' => $total_tether,
                                        'avg_price' => 0,
                                        'amount' => $request->pair_amount,
                                        'total_price' => $toman_amount,
                                        'current_wage' => $wage,
                                        'filled' => 0,
                                        'status' => OrdersList::PENDING_STATUS,
                                    ];

                                    OrderRepository::storeBuyOrders($order_info);

                                    $order_setting = OrderSetting::checkOrderMode();
                                    if($order_setting->mode == 'اتوماتیک')
                                    {
                                        $coinex_order_info = [
                                            'access_id' => '1',
                                            'market' => strtoupper($request->pair_type) . 'USDT',
                                            'type' => 'sell',
                                            'amount' => $request->pair_amount,
                                            'tonce' => now()->toDateTimeString(),
                                            'account_id' => 0,
                                            'client_id' => Auth::guard('api')->user()->mobile
                                        ];

                                        SellExchangeTrait::SellOrderCoinex($coinex_order_info, $request->type, @$request->lang);
                                    }

                                    switch ($request->lang)
                                    {
                                        case 'fa':
                                        default:
                                            return Response::success('تبریک، سفارش شما با موفقیت ثبت شد', null, 200);
                                        case 'en':
                                            return Response::success('congratulation, your order has been registered', null, 200);
                                    }
                                }

                            }
                        }
                    }
                    break;

                case 'بایننس':
                    $market_info = BinanceCurrency::getLastInfo();
                    $data = json_decode($market_info->data);
                    foreach ($data as $market_info) {
                        if ($market_info->symbol == strtoupper($request->pair_type) . 'USDT') {
                            $maker_fee_rate = ($request->pair_amount * $request->pair_price) * 0.1;
                            $invoice = [];
                            $wage = SellExchangeTrait::calculateWage($request->pair_amount, $request->pair_price, $static_percent);
                            $total_tether = SellExchangeTrait::calculateTotalTether($request->pair_amount, $request->pair_price, $maker_fee_rate, $static_percent);
                            $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);
                            switch ($request->receipt_type)
                            {
                                case 'usdt':
                                default:
                                    $invoice =  [
                                        'your_payment' => $request->pair_amount,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $total_tether,
                                        'currency_type' => 'USDT'
                                    ];
                                    break;
                                case 'toman':
                                    $invoice =  [
                                        'your_payment' => $request->pair_amount,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $toman_amount,
                                        'currency_type' => 'IRR'
                                    ];
                                    break;
                            }

                            if($request->request_type == 'invoice') {
                                switch ($request->lang)
                                {
                                    case 'fa':
                                    default:
                                        return Response::success('ارز مورد نظر محاسبه گردید', $invoice, 200);
                                    case 'en':
                                        return Response::success('Your exchange info calculated', $invoice, 200);
                                }
                            }
                            elseif($request->request_type == 'sell'){
                                $user_wallet = $this->userWalletsRepository
                                    ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));
                                if($user_wallet->amount < $request->pair_amount)
                                {
                                    $required_exchange_amount  =  $request->pair_amount - $user_wallet->amount;
                                    $data = [
                                        'required_'.strtolower($request->pair_type).'_amount' => $required_exchange_amount
                                    ];
                                    switch ($request->lang)
                                    {
                                        case 'fa':
                                        default:
                                            return Response::failed('متاسفم، مقدار ارز بیشتری مورد نیاز است', $data, 422, -2);
                                        case 'en':
                                            return Response::failed('sorry, more '.strtolower($request->pair_type).' are needed', $data, 422, -2);
                                    }
                                }
                                else{
                                    $this->userWalletsRepository
                                        ->decreaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);
                                    switch ($request->receipt_type)
                                    {
                                        case 'usdt':
                                            $this->userWalletsRepository
                                                ->increaseWalletAmount(Auth::guard('api')->id(), 'USDT', $total_tether);
                                            break;
                                        case 'toman':
                                        default:
                                            $this->userWalletsRepository
                                                ->increaseWalletAmount(Auth::guard('api')->id(), 'IRR', $toman_amount);
                                            break;
                                    }

                                    $order_info = [
                                        'user_id' => Auth::guard('api')->id(),
                                        'time' => now(),
                                        'market' => strtoupper($request->pair_type).'USDT',
                                        'type' => OrdersList::LIMIT_TYPE,
                                        'role' => OrdersList::SELL_ROLE,
                                        'order_price' => $total_tether,
                                        'avg_price' => 0,
                                        'amount' => $request->pair_amount,
                                        'total_price' => $toman_amount,
                                        'current_wage' => $wage,
                                        'filled' => 0,
                                        'status' => OrdersList::PENDING_STATUS,
                                    ];

                                    OrderRepository::storeBuyOrders($order_info);

                                    $order_setting = OrderSetting::checkOrderMode();
                                    if($order_setting->mode == 'اتوماتیک')
                                    {
                                        $coinex_order_info = [
                                            'access_id' => '1',
                                            'market' => strtoupper($request->pair_type) . 'USDT',
                                            'type' => 'sell',
                                            'amount' => $request->pair_amount,
                                            'tonce' => now()->toDateTimeString(),
                                            'account_id' => 0,
                                            'client_id' => Auth::guard('api')->user()->mobile
                                        ];

                                        SellExchangeTrait::SellOrderCoinex($coinex_order_info, $request->type, @$request->lang);
                                    }

                                    switch ($request->lang)
                                    {
                                        case 'fa':
                                        default:
                                            return Response::success('تبریک، سفارش شما با موفقیت ثبت شد', null, 200);
                                        case 'en':
                                            return Response::success('congratulation, your order has been registered', null, 200);
                                    }
                                }

                            }
                        }
                    }
                    break;
            }

//          end limit invoice or buy exchanges
        }


    }


}
