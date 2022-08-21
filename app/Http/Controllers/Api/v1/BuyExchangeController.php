<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\CacheHelper;
use App\Helpers\PaymentHelper;
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
use App\Repositories\Api\v1\UserRepository;
use App\Repositories\Api\v1\UserWalletsRepository;
use Illuminate\Http\Request;
use App\Traits\BuyExchangeTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class BuyExchangeController extends Controller
{
    use BuyExchangeTrait;

    private $userWalletsRepository;
    private $orderRepository;
    private $userRepository;

    public function __construct(UserWalletsRepository $userWalletsRepository, OrderRepository $orderRepository, UserRepository $userRepository)
    {
        $this->userWalletsRepository = $userWalletsRepository;
        $this->orderRepository = $orderRepository;
        $this->userRepository = $userRepository;
    }

    public function buyExchange(Request $request)
    {
        $tetherPrice = 30000;
        $static_percent = 0.3;

        if ($request->type == 'market') {
//          Market invoice or buy tether
            if ($request->pair_type == 'USDT' || $request->pair_type == 'usdt') {
                $wage_percent = ($static_percent / 100) * $request->pair_amount;
                $total_price = $request->pair_amount * $tetherPrice;
                $recived_tether = $request->pair_amount - $wage_percent;
                $data = [
                    'your_payment' => $total_price,
                    'wage_percent' => $wage_percent,
                    'your_receipt' => $recived_tether
                ];
                if ($request->request_type == 'invoice') {
                    switch ($request->lang) {
                        case 'fa':
                        default:
                            return Response::success('مقدار تتر محاسبه گردید', $data, 200);
                        case 'en':
                            return Response::success('Tether amount calculated', $data, 200);
                    }
                } elseif ($request->request_type == 'buy') {
                    $user_wallet = $this->userWalletsRepository
                        ->getUserWallet(Auth::guard('api')->id(), 'IRR');

                    if ($user_wallet->amount < $total_price) {
                        $required_amount_tomans = $total_price - $user_wallet->amount;
                        $data = [
                            'required_amount_tomans' => $required_amount_tomans
                        ];
                        switch ($request->lang) {
                            case 'fa':
                            default:
                                return Response::failed('متاسفم، مقدار تومان بیشتری مورد نیاز است', $data, 422, -1);
                            case 'en':
                                return Response::failed('sorry, more tomans are needed', $data, 422, -1);
                        }
                    } else {
                        $this->userWalletsRepository
                            ->decreaseWalletAmount(Auth::guard('api')->id(), 'IRR', $total_price);
                        $this->userWalletsRepository
                            ->increaseWalletAmount(Auth::guard('api')->id(), 'USDT', $recived_tether);
                        switch ($request->lang) {
                            case 'fa':
                            default:
                                return Response::success('تبریک، ولت تتری شما با موفقیت شارژ شد', null, 200);
                            case 'en':
                                return Response::success('congratulation, your tether wallet has been charged', null, 200);
                        }
                    }
                }
            }
//          End market invoice or buy tether

//          Market invoice or buy exchanges
            $exchange_setting = ExchangeSetting::getExchangeSettings();
            switch ($exchange_setting->exchange) {
                case 'کوینکس':
                default:
                    // Get coinex market info for find sepcific currency and min amount and taker fee rate
                    $coinex_market_info = CoinexMarketInfoExtracted::findMarketInfo(strtoupper($request->pair_type) . 'USDT');
                    $coinex_market_info = json_decode($coinex_market_info->data);
                    $min_amount = $coinex_market_info->min_amount;
                    $taker_fee_rate = $coinex_market_info->taker_fee_rate;

                    // Find pair type in Currency model
                    $currencies = Currency::getLastInfo();
                    $data = json_decode($currencies->data);
                    $invoice = [];
                    foreach ($data->data->ticker as $exchange => $info) {
                        if ($exchange == strtoupper($request->pair_type) . 'USDT') {
                            $wage = BuyExchangeTrait::calculateWage($request->pair_amount, $info->last, $static_percent);
                            $total_tether = BuyExchangeTrait::calculateTotalTether($request->pair_amount, $info->last, $taker_fee_rate, $static_percent);
                            $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);

                            // Check when we need pay with usdt
                            if ($request->usdt_wallet == 'true') {

                                $user_usdt_wallet = $this->userWalletsRepository
                                    ->getUserWallet(Auth::guard('api')->id(), 'USDT');

                                if ($user_usdt_wallet->amount < $total_tether) {

                                    $required_amount_tether = $total_tether - $user_usdt_wallet->amount;

                                    $invoice = [
                                        'your_payment' => $required_amount_tether,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $request->pair_amount,
                                        'currency_type' => 'USDT'
                                    ];

                                } else {
                                    $invoice = [
                                        'your_payment' => 0,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $request->pair_amount,
                                        'currency_type' => 'USDT'
                                    ];
                                }

                            }

                            // Check when we need pay with irr
                            if ($request->irr_wallet == 'true') {

                                $user_toman_wallet = $this->userWalletsRepository
                                    ->getUserWallet(Auth::guard('api')->id(), 'IRR');

                                // Check toman wallet when user havent credit
                                if ($user_toman_wallet->amount < ($total_tether * $tetherPrice)) {

                                    $required_amount_toman = ($total_tether * $tetherPrice) - $user_toman_wallet->amount;

                                    // Decrease wage
                                    $required_amount_toman = $required_amount_toman - ($wage * $tetherPrice);

                                    // Store toman thats need
                                    $this->userRepository::increaseCredit($required_amount_toman);

                                    // Store currency thats need
                                    $this->userRepository::increaseCurrency($request->pair_amount);

                                    // Store currency type
                                    $this->userRepository::increaseCurrencyType($request->pair_type);

                                    CacheHelper::createCache( Auth::guard('api')->user()->mobile, '300');

                                    $payment_url = PaymentHelper::zarinpal( round($required_amount_toman), null, []);

                                    $invoice = [
                                        'your_payment' => $required_amount_toman,
                                        'payment_url' => $payment_url,
                                        'wage_percent' => $wage * $tetherPrice,
                                        'your_receipt' => $request->pair_amount,
                                        'currency_type' => 'IRR'
                                    ];


                                } else {
                                    $invoice = [
                                        'your_payment' => 0,
                                        'wage_percent' => $wage,
                                        'your_receipt' => $request->pair_amount,
                                        'currency_type' => 'IRR'
                                    ];
                                }

                            }

                            // Default invoice
                            if (count($invoice) == 0) {

                                // Decrease wage
                                $toman_amount = $toman_amount - ($wage * $tetherPrice);

                                // Store toman thats need
                                $this->userRepository::increaseCredit($toman_amount);

                                // Store currency thats need
                                $this->userRepository::increaseCurrency($request->pair_amount);

                                // Store currency type
                                $this->userRepository::increaseCurrencyType($request->pair_type);

                                CacheHelper::createCache( Auth::guard('api')->user()->mobile, '300');

                                $payment_url = PaymentHelper::zarinpal( round($toman_amount), null, []);
                                $invoice = [
                                    'your_payment' => $toman_amount,
                                    'payment_url' => $payment_url,
                                    'wage_percent' => $wage * $tetherPrice,
                                    'your_receipt' => $request->pair_amount,
                                    'currency_type' => 'IRR'
                                ];
                            }

                        }
                    }
                    if ($request->request_type == 'invoice') {

                        // Append min amount to invoice
                        if ($request->pair_amount < $min_amount)
                            $invoice = array_merge($invoice, [
                                'min_required_pair_amount' => $min_amount
                            ]);

                        switch ($request->lang) {
                            case 'fa':
                            default:
                                return Response::success('ارز مورد نظر محاسبه گردید', $invoice, 200);
                            case 'en':
                                return Response::success('Your exchange info calculated', $invoice, 200);
                        }

                    } elseif ($request->request_type == 'buy') {

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

                        $user_usdt_wallet = $this->userWalletsRepository
                            ->getUserWallet(Auth::guard('api')->id(), 'USDT');

                        $user_toman_wallet = $this->userWalletsRepository
                            ->getUserWallet(Auth::guard('api')->id(), 'IRR');

                        // When user select usdt wallet
                        if ($request->usdt_wallet == true)
                            if ($user_usdt_wallet->amount < $total_tether) {

                                // Return tether that's need
                                $required_amount_tether = $total_tether - $user_usdt_wallet->amount;
                                $data = [
                                    'required_amount_tether' => $required_amount_tether
                                ];
                                switch ($request->lang) {
                                    case 'fa':
                                    default:
                                        return Response::failed('متاسفم، مقدار تتری بیشتری مورد نیاز است', $data, 422, -2);
                                    case 'en':
                                        return Response::failed('sorry, more tether are needed', $data, 422, -2);
                                }

                            } else {
                                // When user have usdt to buy currency
                                $this->userWalletsRepository
                                    ->decreaseWalletAmount(Auth::guard('api')->id(), 'USDT', $total_tether);
                                $this->userWalletsRepository
                                    ->increaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);

                                $order_setting = OrderSetting::checkOrderMode();
                                $automatic_mode = 'اتوماتیک';
                                $coinex_order_result = 0;
                                if ($order_setting->mode == $automatic_mode) {
                                    $coinex_order_info = [
                                        'access_id' => '1',
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => 'buy',
                                        'amount' => $total_tether,
                                        'tonce' => now()->toDateTimeString(),
                                        'account_id' => 0,
                                        'client_id' => @Auth::guard('api')->user()->mobile
                                    ];

                                    $coinex_order_result = BuyExchangeTrait::coinexBuyOrder($coinex_order_info, $request->type, @$request->lang);

                                } else {

                                    $order_info = [
                                        'user_id' => Auth::guard('api')->id(),
                                        'time' => now(),
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => OrdersList::MARKET_TYPE,
                                        'role' => OrdersList::BUY_ROLE,
                                        'order_price' => $total_tether,
                                        'avg_price' => 0,
                                        'amount' => $request->pair_amount,
                                        'total_price' => $toman_amount,
                                        'current_wage' => $wage,
                                        'filled' => 0,
                                        'status' => OrdersList::PENDING_STATUS,
                                    ];

                                    OrderRepository::storeBuyOrders($order_info);
                                }

                                if ($coinex_order_result == 0) {

                                    $order_info = [
                                        'user_id' => Auth::guard('api')->id(),
                                        'time' => now(),
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => OrdersList::MARKET_TYPE,
                                        'role' => OrdersList::BUY_ROLE,
                                        'order_price' => $total_tether,
                                        'avg_price' => 0,
                                        'amount' => $request->pair_amount,
                                        'total_price' => $toman_amount,
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

                        // When user select toman wallet
                        if ($request->irr_wallet == true)
                            if ($user_toman_wallet->amount < $toman_amount) {

                                // Return toman that's need
                                $required_amount_toman = $toman_amount - $user_toman_wallet->amount;
                                $data = [
                                    'required_amount_tether' => $required_amount_toman
                                ];
                                switch ($request->lang) {
                                    case 'fa':
                                    default:
                                        return Response::failed('متاسفم، مقدار تومان بیشتری مورد نیاز است', $data, 422, -2);
                                    case 'en':
                                        return Response::failed('sorry, more toman are needed', $data, 422, -2);
                                }

                            } else {

                                // When user have toman to buy currency
                                $this->userWalletsRepository
                                    ->decreaseWalletAmount(Auth::guard('api')->id(), 'IRR', $total_tether * $tetherPrice);
                                $this->userWalletsRepository
                                    ->increaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);


                                $order_setting = OrderSetting::checkOrderMode();
                                $coinex_order_result = 0;
                                if ($order_setting->mode == 'اتوماتیک') {
                                    $coinex_order_info = [
                                        'access_id' => '1',
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => 'buy',
                                        'amount' => $request->pair_amount,
                                        'tonce' => now()->toDateTimeString(),
                                        'account_id' => 0,
                                        'client_id' => @Auth::guard('api')->user()->mobile
                                    ];

                                    $coinex_order_result = BuyExchangeTrait::coinexBuyOrder($coinex_order_info, $request->type, @$request->lang);

                                }
                                if ($coinex_order_result == 0) {
                                    // Store real order

                                    $order_info = [
                                        'user_id' => Auth::guard('api')->id(),
                                        'time' => now(),
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => OrdersList::MARKET_TYPE,
                                        'role' => OrdersList::BUY_ROLE,
                                        'order_price' => $total_tether,
                                        'avg_price' => 0,
                                        'amount' => $request->pair_amount,
                                        'total_price' => $toman_amount,
                                        'current_wage' => $wage,
                                        'filled' => 0,
                                        'status' => OrdersList::DONE_STATUS,
                                    ];

                                    OrderRepository::storeBuyOrders($order_info);

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
                            // End user buy currency with toman

                        }

                    break;

                case 'کوکوین':
                    $market_info = KucoinCurrency::getLastInfo();
                    $data = json_decode($market_info->data);
                    foreach ($data->data->ticker as $market_info) {
                        if ($market_info->symbol == strtoupper($request->pair_type) . '-USDT') {
                            $taker_fee_rate = $market_info->takerFeeRate;
                            $invoice = [];
                            $wage = BuyExchangeTrait::calculateWage($request->pair_amount, $market_info->last, $static_percent);
                            $total_tether = BuyExchangeTrait::calculateTotalTether($request->pair_amount, $market_info->last, $taker_fee_rate, $static_percent);
                            $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);
                            $invoice = [
                                'your_payment' => $toman_amount,
                                'wage_percent' => $wage,
                                'your_receipt' => $request->pair_amount
                            ];

                            if ($request->request_type == 'invoice') {
                                switch ($request->lang) {
                                    case 'fa':
                                    default:
                                        return Response::success('ارز مورد نظر محاسبه گردید', $invoice, 200);
                                    case 'en':
                                        return Response::success('Your exchange info calculated', $invoice, 200);
                                }
                            } elseif ($request->request_type == 'buy') {
                                $user_usdt_wallet = $this->userWalletsRepository
                                    ->getUserWallet(Auth::guard('api')->id(), 'USDT');
                                $user_toman_wallet = $this->userWalletsRepository
                                    ->getUserWallet(Auth::guard('api')->id(), 'IRR');
                                if ($user_usdt_wallet->amount < $total_tether) {

                                    // Check toman wallet when user havent credit
                                    if ($user_toman_wallet->amount < ($total_tether * $tetherPrice)) {
                                        $required_amount_tether = $total_tether - $user_usdt_wallet->amount;
                                        $data = [
                                            'required_amount_tether' => $required_amount_tether
                                        ];
                                        switch ($request->lang) {
                                            case 'fa':
                                            default:
                                                return Response::failed('متاسفم، مقدار تتری بیشتری مورد نیاز است', $data, 422, -2);
                                            case 'en':
                                                return Response::failed('sorry, more tether are needed', $data, 422, -2);
                                        }
                                    } else {
                                        $this->userWalletsRepository
                                            ->decreaseWalletAmount(Auth::guard('api')->id(), 'IRR', $total_tether * $tetherPrice);
                                        $this->userWalletsRepository
                                            ->increaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);

                                        $order_info = [
                                            'user_id' => Auth::guard('api')->id(),
                                            'time' => now(),
                                            'market' => strtoupper($request->pair_type) . 'USDT',
                                            'type' => OrdersList::MARKET_TYPE,
                                            'role' => OrdersList::BUY_ROLE,
                                            'order_price' => $total_tether,
                                            'avg_price' => 0,
                                            'amount' => $request->pair_amount,
                                            'total_price' => $toman_amount,
                                            'current_wage' => $wage,
                                            'filled' => 0,
                                            'status' => OrdersList::PENDING_STATUS,
                                        ];
                                        OrderRepository::storeBuyOrders($order_info);

                                        switch ($request->lang) {
                                            case 'fa':
                                            default:
                                                return Response::success('تبریک، ولت شما با موفقیت شارژ شد', null, 200);
                                            case 'en':
                                                return Response::success('congratulation, your ' . strtolower($request->pair_type) . ' wallet has been charged', null, 200);
                                        }
                                    }
                                    // End check toman wallet and operation

                                } else {
                                    $this->userWalletsRepository
                                        ->decreaseWalletAmount(Auth::guard('api')->id(), 'USDT', $total_tether);
                                    $this->userWalletsRepository
                                        ->increaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);
                                    $order_info = [
                                        'user_id' => Auth::guard('api')->id(),
                                        'time' => now(),
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => OrdersList::MARKET_TYPE,
                                        'role' => OrdersList::BUY_ROLE,
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
                                    if ($order_setting->mode == 'اتوماتیک') {
                                        $coinex_order_info = [
                                            'access_id' => '1',
                                            'market' => strtoupper($request->pair_type) . 'USDT',
                                            'type' => 'buy',
                                            'amount' => $request->pair_amount,
                                            'tonce' => now()->toDateTimeString(),
                                            'account_id' => 0,
                                            'client_id' => @Auth::guard('api')->user()->mobile
                                        ];

                                        BuyExchangeTrait::coinexBuyOrder($coinex_order_info, $request->type, @$request->lang);
                                    }

                                    // Send message of user order
                                    SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['market_order'], $request->pair_amount);

                                    switch ($request->lang) {
                                        case 'fa':
                                        default:
                                            return Response::success('تبریک، ولت شما با موفقیت شارژ شد', null, 200);
                                        case 'en':
                                            return Response::success('congratulation, your ' . strtolower($request->pair_type) . ' wallet has been charged', null, 200);
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
                            $taker_fee_rate = $request->pair_amount * 0.1;
                            $invoice = [];
                            $wage = BuyExchangeTrait::calculateWage($request->pair_amount, $market_info->price, $static_percent);
                            $total_tether = BuyExchangeTrait::calculateTotalTether($request->pair_amount, $market_info->price, $taker_fee_rate, $static_percent);
                            $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);
                            $invoice = [
                                'your_payment' => $toman_amount,
                                'wage_percent' => $wage,
                                'your_receipt' => $request->pair_amount
                            ];

                            if ($request->request_type == 'invoice') {
                                switch ($request->lang) {
                                    case 'fa':
                                    default:
                                        return Response::success('ارز مورد نظر محاسبه گردید', $invoice, 200);
                                    case 'en':
                                        return Response::success('Your exchange info calculated', $invoice, 200);
                                }
                            } elseif ($request->request_type == 'buy') {
                                $user_usdt_wallet = $this->userWalletsRepository
                                    ->getUserWallet(Auth::guard('api')->id(), 'USDT');
                                $user_toman_wallet = $this->userWalletsRepository
                                    ->getUserWallet(Auth::guard('api')->id(), 'IRR');
                                if ($user_usdt_wallet->amount < $total_tether) {

                                    // Check toman wallet when user havent credit
                                    if ($user_toman_wallet->amount < ($total_tether * $tetherPrice)) {
                                        $required_amount_tether = $total_tether - $user_usdt_wallet->amount;
                                        $data = [
                                            'required_amount_tether' => $required_amount_tether
                                        ];
                                        switch ($request->lang) {
                                            case 'fa':
                                            default:
                                                return Response::failed('متاسفم، مقدار تتری بیشتری مورد نیاز است', $data, 422, -2);
                                            case 'en':
                                                return Response::failed('sorry, more tether are needed', $data, 422, -2);
                                        }
                                    } else {
                                        $this->userWalletsRepository
                                            ->decreaseWalletAmount(Auth::guard('api')->id(), 'IRR', $total_tether * $tetherPrice);
                                        $this->userWalletsRepository
                                            ->increaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);

                                        $order_info = [
                                            'user_id' => Auth::guard('api')->id(),
                                            'time' => now(),
                                            'market' => strtoupper($request->pair_type) . 'USDT',
                                            'type' => OrdersList::MARKET_TYPE,
                                            'role' => OrdersList::BUY_ROLE,
                                            'order_price' => $total_tether,
                                            'avg_price' => 0,
                                            'amount' => $request->pair_amount,
                                            'total_price' => $toman_amount,
                                            'current_wage' => $wage,
                                            'filled' => 0,
                                            'status' => OrdersList::PENDING_STATUS,
                                        ];
                                        OrderRepository::storeBuyOrders($order_info);

                                        // Send message of user order
                                        SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['market_order'], $request->pair_amount);

                                        switch ($request->lang) {
                                            case 'fa':
                                            default:
                                                return Response::success('تبریک، ولت شما با موفقیت شارژ شد', null, 200);
                                            case 'en':
                                                return Response::success('congratulation, your ' . strtolower($request->pair_type) . ' wallet has been charged', null, 200);
                                        }
                                    }
                                    // End check toman wallet and operation

                                } else {
                                    $this->userWalletsRepository
                                        ->decreaseWalletAmount(Auth::guard('api')->id(), 'USDT', $total_tether);
                                    $this->userWalletsRepository
                                        ->increaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $request->pair_amount);
                                    $order_info = [
                                        'user_id' => Auth::guard('api')->id(),
                                        'time' => now(),
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => OrdersList::MARKET_TYPE,
                                        'role' => OrdersList::BUY_ROLE,
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
                                    if ($order_setting->mode == 'اتوماتیک') {
                                        $coinex_order_info = [
                                            'access_id' => '1',
                                            'market' => strtoupper($request->pair_type) . 'USDT',
                                            'type' => 'buy',
                                            'amount' => $request->pair_amount,
                                            'tonce' => now()->toDateTimeString(),
                                            'account_id' => 0,
                                            'client_id' => @Auth::guard('api')->user()->mobile
                                        ];

                                        BuyExchangeTrait::coinexBuyOrder($coinex_order_info, $request->type, @$request->lang);
                                    }
                                    switch ($request->lang) {
                                        case 'fa':
                                        default:
                                            return Response::success('تبریک، ولت شما با موفقیت شارژ شد', null, 200);
                                        case 'en':
                                            return Response::success('congratulation, your ' . strtolower($request->pair_type) . ' wallet has been charged', null, 200);
                                    }

                                }
                            }
                        }
                    }
                    break;
            }

//          end market invoice or buy exchanges
        }

        if ($request->type == 'limit' && $request->has('pair_price')) {
//          Limit invoice or buy tether
            if ($request->pair_type == 'USDT' || $request->pair_type == 'usdt') {
                $wage_percent = ($static_percent / 100) * $request->pair_amount;
                $total_price = $request->pair_amount * $request->pair_price;
                $recived_tether = $request->pair_amount - $wage_percent;
                $data = [
                    'your_payment' => $total_price,
                    'wage_percent' => $wage_percent,
                    'your_receipt' => $recived_tether
                ];
                if ($request->request_type == 'invoice') {
                    switch ($request->lang) {
                        case 'fa':
                        default:
                            return Response::success('مقدار تتر محاسبه گردید', $data, 200);
                        case 'en':
                            return Response::success('Tether amount calculated', $data, 200);
                    }
                } elseif ($request->request_type == 'buy') {
                    $order_info = [
                        'user_id' => Auth::guard('api')->id(),
                        'time' => now(),
                        'market' => 'USDT',
                        'type' => OrdersList::LIMIT_TYPE,
                        'role' => OrdersList::BUY_ROLE,
                        'order_price' => $total_price,
                        'avg_price' => 0,
                        'amount' => $recived_tether,
                        'total_price' => $total_price,
                        'current_wage' => $wage_percent,
                        'filled' => 0,
                        'status' => OrdersList::PENDING_STATUS,
                    ];

                    OrderRepository::storeBuyOrders($order_info);

                    $order_setting = OrderSetting::checkOrderMode();
                    if ($order_setting->mode == 'اتوماتیک') {
                        $coinex_order_info = [
                            'access_id' => '1',
                            'market' => 'USDT',
                            'type' => 'buy',
                            'amount' => $recived_tether,
                            'tonce' => now()->toDateTimeString(),
                            'account_id' => 0,
                            'client_id' => @Auth::guard('api')->user()->mobile
                        ];

                        BuyExchangeTrait::coinexBuyOrder($coinex_order_info, $request->type, @$request->lang);
                    }

                    switch ($request->lang) {
                        case 'fa':
                        default:
                            return Response::success('تبریک، سفارش شما با موفقیت ثبت شد', $data);
                        case 'en':
                            return Response::success('congratulation, your order has been registered', null, 200);
                    }

                }
            }
//          end limit invoice or buy tether

//          Limit invoice or buy exchanges
            $exchange_setting = ExchangeSetting::getExchangeSettings();
            switch ($exchange_setting->exchange) {
                case 'کوینکس':
                default:
                    // Get coinex market info for find sepcific currency and min amount and taker fee rate
                    $coinex_market_info = CoinexMarketInfoExtracted::findMarketInfo(strtoupper($request->pair_type) . 'USDT');
                    $coinex_market_info = json_decode($coinex_market_info->data);
                    $min_amount = $coinex_market_info->min_amount;
                    $taker_fee_rate = $coinex_market_info->taker_fee_rate;

                    $invoice = [];
                    $wage = BuyExchangeTrait::calculateWage($request->pair_amount, $request->pair_price, $static_percent);
                    $total_tether = BuyExchangeTrait::calculateTotalTether($request->pair_amount, $request->pair_price, $taker_fee_rate, $static_percent);
                    $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);

                    $invoice = [
                        'your_payment' => $total_tether,
                        'wage_percent' => $wage,
                        'your_receipt' => $request->pair_amount,
                        'currency_type' => 'USDT'
                    ];

                    if ($request->request_type == 'invoice') {
                        switch ($request->lang) {
                            case 'fa':
                            default:
                                return Response::success('اطلاعات ارز مورد نظر محاسبه گردید', $invoice, 200);
                            case 'en':
                                return Response::success('Your exchange info calculated', $invoice, 200);
                        }
                    } elseif ($request->request_type == 'buy') {
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
                        $order_info = [
                            'user_id' => Auth::guard('api')->id(),
                            'time' => now(),
                            'market' => strtoupper($request->pair_type) . 'USDT',
                            'type' => OrdersList::LIMIT_TYPE,
                            'role' => OrdersList::BUY_ROLE,
                            'order_price' => $toman_amount,
                            'avg_price' => $toman_amount,
                            'amount' => $request->pair_amount,
                            'total_price' => $toman_amount,
                            'current_wage' => $wage,
                            'filled' => 0,
                            'status' => OrdersList::PENDING_STATUS
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
                                'client_id' => @Auth::guard('api')->user()->mobile
                            ];

                            BuyExchangeTrait::coinexBuyOrder($coinex_order_info, $request->type, @$request->lang);
                        }

                        // Send message of user order
                        SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['limit_order'], $request->pair_amount);

                        switch ($request->lang) {
                            case 'fa':
                            default:
                                return Response::success('تبریک، سفارش شما با موفقیت ثبت شد', null, 200);
                            case 'en':
                                return Response::success('congratulation, your order has been registered', null, 200);
                        }

                    }


                    break;

                case 'کوکوین':
                    $market_info = KucoinCurrency::getLastInfo();
                    $data = json_decode($market_info->data);
                    foreach ($data->data->ticker as $market_info) {
                        if ($market_info->symbol == strtoupper($request->pair_type) . '-USDT') {
                            $taker_fee_rate = $market_info->takerFeeRate;
                            $invoice = [];
                            $wage = BuyExchangeTrait::calculateWage($request->pair_amount, $request->pair_price, $static_percent);
                            $total_tether = BuyExchangeTrait::calculateTotalTether($request->pair_amount, $request->pair_price, $taker_fee_rate, $static_percent);
                            $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);
                            $invoice = [
                                'your_payment' => $total_tether,
                                'wage_percent' => $wage,
                                'your_receipt' => $request->pair_amount,
                                'currency_type' => 'USDT'
                            ];

                            if ($request->request_type == 'invoice') {
                                switch ($request->lang) {
                                    case 'fa':
                                    default:
                                        return Response::success('اطلاعات ارز مورد نظر محاسبه گردید', $invoice, 200);
                                    case 'en':
                                        return Response::success('Your exchange info calculated', $invoice, 200);
                                }
                            } elseif ($request->request_type == 'buy') {
                                $order_info = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'time' => now(),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => OrdersList::LIMIT_TYPE,
                                    'role' => OrdersList::BUY_ROLE,
                                    'order_price' => $toman_amount,
                                    'avg_price' => $toman_amount,
                                    'amount' => $request->pair_amount,
                                    'total_price' => $toman_amount,
                                    'current_wage' => $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::PENDING_STATUS
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
                                        'client_id' => @Auth::guard('api')->user()->mobile
                                    ];

                                    BuyExchangeTrait::coinexBuyOrder($coinex_order_info, $request->type, @$request->lang);
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
                    }
                    break;

                case 'بایننس':
                    $market_info = BinanceCurrency::getLastInfo();
                    $data = json_decode($market_info->data);
                    foreach ($data as $market_info) {
                        if ($market_info->symbol == strtoupper($request->pair_type) . 'USDT') {
                            $taker_fee_rate = $request->pair_amount * 0.1;
                            $invoice = [];
                            $wage = BuyExchangeTrait::calculateWage($request->pair_amount, $request->pair_price, $static_percent);
                            $total_tether = BuyExchangeTrait::calculateTotalTether($request->pair_amount, $request->pair_price, $taker_fee_rate, $static_percent);
                            $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tetherPrice);
                            $invoice = [
                                'your_payment' => $total_tether,
                                'wage_percent' => $wage,
                                'your_receipt' => $request->pair_amount,
                                'currency_type' => 'USDT'
                            ];

                            if ($request->request_type == 'invoice') {
                                switch ($request->lang) {
                                    case 'fa':
                                    default:
                                        return Response::success('اطلاعات ارز مورد نظر محاسبه گردید', $invoice, 200);
                                    case 'en':
                                        return Response::success('Your exchange info calculated', $invoice, 200);
                                }
                            } elseif ($request->request_type == 'buy') {
                                $order_info = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'time' => now(),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => OrdersList::LIMIT_TYPE,
                                    'role' => OrdersList::BUY_ROLE,
                                    'order_price' => $toman_amount,
                                    'avg_price' => $toman_amount,
                                    'amount' => $request->pair_amount,
                                    'total_price' => $toman_amount,
                                    'current_wage' => $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::PENDING_STATUS
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
                                        'client_id' => @Auth::guard('api')->user()->mobile
                                    ];

                                    BuyExchangeTrait::coinexBuyOrder($coinex_order_info, $request->type, @$request->lang);
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
                    }
                    break;

//          end limit invoice or buy exchanges
            }

        }
    }


}
