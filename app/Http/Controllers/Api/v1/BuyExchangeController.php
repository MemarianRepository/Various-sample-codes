<?php

namespace App\Http\Controllers\Api\v1;

use App\Events\OrderVolumenScore;
use App\Events\pay_commision;
use App\Helpers\CacheHelper;
use App\Helpers\FreezeHelper;
use App\Helpers\PaymentHelper;
use App\Helpers\RequestHelper;
use App\Helpers\SmsHelper;
use App\Http\Controllers\Controller;
use App\Models\Api\v1\AdminUsdtPrice;
use App\Models\Api\v1\BinanceCurrency;
use App\Models\Api\v1\CoinexMarketInfoExtracted;
use App\Models\Api\v1\DoneOrdersList;
use App\Models\Api\v1\ExchangeList;
use App\Models\Api\v1\ExchangeSetting;
use App\Models\Api\v1\Income;
use App\Models\Api\v1\IncomeLog;
use App\Models\Api\v1\KucoinCurrency;
use App\Models\Api\v1\OrdersList;
use App\Models\Api\v1\OrdersLog;
use App\Models\Api\v1\UsdtPrice;
use App\Models\Api\v1\UserWallets;
use App\Models\Currency;
use App\Models\OrderSetting;
use App\Repositories\Api\v1\OrderRepository;
use App\Repositories\Api\v1\UserRepository;
use App\Repositories\Api\v1\UserWalletsRepository;
use Illuminate\Http\Request;
use App\Traits\BuyExchangeTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Elegant\Sanitizer\Sanitizer;
use Illuminate\Support\Facades\Validator;
use Lin\Coinex\CoinexExchange;

/**
 * @group Trade section
 * Api to buy and sell currency
 **/
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

    /**
     * Api to buy currency
     * @bodyParam pair_type string
     * @bodyParam type string
     * @bodyParam pair_price int
     * @bodyParam pair_amount int
     * @bodyParam request_type string
     * @bodyParam irr_wallet boolean
     * @bodyParam usdt_wallet boolean
     **/
    public function market(Request $request)
    {
        $santizier = new Sanitizer($request->all(), [
            'pair_type' => 'strip_tags',
            'type' => 'strip_tags',
            'pair_price' => 'strip_tags',
            'pair_amount' => 'strip_tags',
            'request_type' => 'strip_tags',
            'irr_wallet' => 'strip_tags',
            'usdt_wallet' => 'strip_tags'
        ]);

        $request_sanitized = $santizier->sanitize();

        $validator = Validator::make($request_sanitized, [
            'pair_type' => ['string'],
            'type' => ['string'],
            'pair_price' => ['numeric'],
            'pair_amount' => ['numeric'],
            'request_type' => ['string']
        ]);

        if ($validator->fails()) {
            return Response::failed($validator->errors()->toArray(), null, 422, -1);
        }

        $tether_price = UsdtPrice::get()->quantity / 10;
        $static_percent = \config('bitazar.coinex.wage_percent');


//  buy tether
        if ($request->pair_type == 'USDT' || $request->pair_type == 'usdt') {
            $wage_percent = ($static_percent / 100) * $request->pair_amount;
            $total_price = $request->pair_amount * $tether_price;
            $recived_tether = $request->pair_amount - $wage_percent;

            // Increase wage
            $required_amount_toman = $total_price + ($wage_percent * $tether_price);

            // Store toman thats need
            $this->userRepository::increaseCredit($required_amount_toman);

            // Store currency thats need
            $this->userRepository::increaseCurrency($request->pair_amount);

            // Store currency type
            $this->userRepository::increaseCurrencyType($request->pair_type);

            CacheHelper::createCache( Auth::guard('api')->user()->mobile, '300');

            $payment_url = PaymentHelper::nextPay( round($required_amount_toman), null, ['user_id' => Auth::guard('api')->user()]);

            $data = [
                'your_payment' => $required_amount_toman,
                'payment_url' => $payment_url,
                'wage_percent' => $wage_percent * $tether_price,
                'currency_type_wage_percent' => 'تومان',
                'your_receipt' => $recived_tether,
                'currency_type_your_receipt' => 'تتر',
                'currency_type' => 'تومان'
            ];


            if ($request->request_type == 'invoice') {

                return Response::success(__('trade_exchange_success_tether_amount_calculated'), $data, 200);

            } elseif ($request->request_type == 'buy') {

                $freeze_amount = FreezeHelper::getFreezeAmount('IRR');

                $user_wallet = $this->userWalletsRepository
                    ->getUserWallet(Auth::guard('api')->id(), 'IRR');

                if (($user_wallet->amount - $freeze_amount) < $total_price) {
                    $required_amount_tomans = $total_price - $user_wallet->amount;
                    $data = [
                        'required_amount_tomans' => $required_amount_tomans
                    ];

                    return Response::failed(__('buy_exchange_failed_toman_are_needed'), $data, 422, -1);

                } else {
                    $this->userWalletsRepository
                        ->decreaseWalletAmount(Auth::guard('api')->id(), 'IRR', $total_price);
                    $this->userWalletsRepository
                        ->increaseWalletAmount(Auth::guard('api')->id(), 'USDT', $recived_tether);

                    //event(new OrderVolumenScore(Auth::guard('api')->user()));

                    $currency = ExchangeList::findBySymbol(strtoupper($request->pair_type));
                    event(new pay_commision(Auth::guard('api')->user(), ($static_percent / 100) * $order_amount, $currency->id));

                    return Response::success(__('trade_exchange_success_tether_charged'), null, 200);

                }
            }
        }
//          End market invoice or buy tether

//  buy currency
        $exchange_setting = ExchangeSetting::getExchangeSettings();
        switch ($exchange_setting->exchange) {
            case 'کوینکس':
            default:
                // Get coinex market info for find sepcific currency and min amount and taker fee rate
                $coinex_market_info = CoinexMarketInfoExtracted::findMarketInfo(strtoupper($request->pair_type) . 'USDT');
                $coinex_market_info = json_decode($coinex_market_info->data);
                $min_amount = $coinex_market_info->min_amount;
                $taker_fee_rate = \config('bitazar.coinex.network_wage_percent');

                $pricing_decimal = $coinex_market_info->pricing_decimal;
                $trading_decimal = $coinex_market_info->trading_decimal;

                // Request to get ticker from coinex
                $params = [
                    'market' => strtoupper($request->pair_type) . 'USDT'
                ];

                $result = RequestHelper::send('https://api.coinex.com/v1/market/ticker', 'get', $params, null);

                $info = (object) $result->data->ticker;


                $invoice = [];

                switch ($request->amount_type) {
                    case 'usdt':
                        $pair_amount = $request->pair_amount;
                        break;
                    case 'toman':
                    default:
                        $pair_amount = $request->pair_amount / $tether_price;
                        break;
                }

                $order_amount = BuyExchangeTrait::calculateOrderAmount($pair_amount, $info->last);
                $wage = BuyExchangeTrait::calculateWage($order_amount, $info->last, $static_percent);
                $total_tether = BuyExchangeTrait::calculateTotalTether($pair_amount, null, $taker_fee_rate, $static_percent);
                $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tether_price);
                $taker_fee_rate = BuyExchangeTrait::calculateNetworkWage($pair_amount, $taker_fee_rate);

                if ($order_amount < $min_amount) {

                    $data = [
                        'min_required_pair_amount' => $min_amount,
                        'your_pair_amount' => $order_amount,
                        'toman_that_need' => ($min_amount * $info->last) * $tether_price
                    ];

                    return Response::failed(__('trade_exchange_failed_minimum_order'), $data, 422, -4);
                }

                // Check when we need pay with usdt
                if ($request->usdt_wallet == 'true') {

                    $user_usdt_wallet = $this->userWalletsRepository
                        ->getUserWallet(Auth::guard('api')->id(), 'USDT');

                    if ($user_usdt_wallet->amount < $total_tether) {

                        $required_amount_tether = $total_tether - $user_usdt_wallet->amount;

                        $invoice = [
                            'your_payment' => $total_tether,
                            'wage_percent' => $wage,
                            'network_wage' => $taker_fee_rate,
                            'currency_type_wage_percent' => 'تتر',
                            'your_receipt' => $order_amount,
                            'currency_type_your_receipt' => strtoupper($request->pair_type),
                            'currency_type' => 'تتر',
                            'payment_type' => 'wallet'
                        ];

                    } else {
                        $invoice = [
                            'your_payment' => $total_tether,
                            'wage_percent' => $wage,
                            'network_wage' => $taker_fee_rate,
                            'currency_type_wage_percent' => 'تتر',
                            'your_receipt' => $order_amount,
                            'currency_type_your_receipt' => strtoupper($request->pair_type),
                            'currency_type' => 'تتر',
                            'payment_type' => 'wallet'
                        ];
                    }

                }

                // Check when we need pay with irr
                if ($request->irr_wallet == 'true') {

                    $user_toman_wallet = $this->userWalletsRepository
                        ->getUserWallet(Auth::guard('api')->id(), 'IRR');

                    // Check toman wallet when user havent credit
                    if ($user_toman_wallet->amount < ($total_tether * $tether_price)) {

                        $required_amount_toman = ($total_tether * $tether_price) - $user_toman_wallet->amount;

                        // Decrease wage
                        $required_amount_toman = $required_amount_toman - ($wage * $tether_price);

                        // Store toman thats need
                        $this->userRepository::increaseCredit($required_amount_toman);

                        // Store currency thats need
                        $this->userRepository::increaseCurrency($order_amount);

                        // Store currency type
                        $this->userRepository::increaseCurrencyType($request->pair_type);

                        CacheHelper::createCache( Auth::guard('api')->user()->mobile, '300');

                        $payment_url = PaymentHelper::nextPay( round($required_amount_toman), null, ['user_id' => Auth::guard('api')->user()]);

                        $invoice = [
                            'your_payment' => $toman_amount,
                            'payment_url' => $payment_url,
                            'wage_percent' => $wage * $tether_price,
                            'network_wage' => $taker_fee_rate * $tether_price,
                            'currency_type_wage_percent' => 'تومان',
                            'your_receipt' => $order_amount,
                            'currency_type_your_receipt' => strtoupper($request->pair_type),
                            'currency_type' => 'تومان',
                            'payment_type' => 'online'
                        ];


                    } else {
                        $invoice = [
                            'your_payment' => $toman_amount,
                            'wage_percent' => $wage * $tether_price,
                            'network_wage' => $taker_fee_rate * $tether_price,
                            'currency_type_wage_percent' => 'تومان',
                            'your_receipt' => $order_amount,
                            'currency_type_your_receipt' => strtoupper($request->pair_type),
                            'currency_type' => 'تومان',
                            'payment_type' => 'wallet'
                        ];
                    }

                }

                // Default invoice
                if (count($invoice) == 0) {

                    // Decrease wage
                    $toman_amount = (int)$toman_amount - (int)$wage * $tether_price;

                    // Store toman thats need
                    $this->userRepository::increaseCredit($toman_amount);

                    // Store currency thats need
                    $this->userRepository::increaseCurrency($order_amount);

                    // Store currency type
                    $this->userRepository::increaseCurrencyType($request->pair_type);

                    CacheHelper::createCache( Auth::guard('api')->user()->mobile, '300');

                    $payment_url = PaymentHelper::nextPay( round($toman_amount), null, ['user_id' => Auth::guard('api')->user()]);
                    $invoice = [
                        'your_payment' => $toman_amount,
                        'payment_url' => $payment_url,
                        'wage_percent' => $wage * $tether_price,
                        'network_wage' => $taker_fee_rate * $tether_price,
                        'currency_type_wage_percent' => 'تومان',
                        'your_receipt' => $order_amount,
                        'currency_type_your_receipt' => strtoupper($request->pair_type),
                        'currency_type' => 'تومان',
                        'payment_type' => 'online'
                    ];
                }

                if ($request->request_type == 'invoice') {

                    // Append min amount to invoice
                    if ($pair_amount < $min_amount)
                        $invoice = array_merge($invoice, [
                            'min_required_pair_amount' => $min_amount
                        ]);

                    return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);


                } elseif ($request->request_type == 'buy') {

                    // Check user amount with minimum amount of order
                    if ($order_amount < $min_amount) {

                        $data = [
                            'min_required_pair_amount' => $min_amount,
                            'your_pair_amount' => $order_amount,
                            'tether_that_need' => $min_amount * $info->last
                        ];

                        return Response::failed(__('trade_exchange_failed_minimum_order'), $data, 422, -4);
                    }

                    $irr_freeze_amount = FreezeHelper::getFreezeAmount('IRR');
                    $usdt_freeze_amount = FreezeHelper::getFreezeAmount('USDT');

                    $user_usdt_wallet = $this->userWalletsRepository
                        ->getUserWallet(Auth::guard('api')->id(), 'USDT');

                    $user_toman_wallet = $this->userWalletsRepository
                        ->getUserWallet(Auth::guard('api')->id(), 'IRR');

                    // When user select usdt wallet
                    if ($request->usdt_wallet == true)
                        if (($user_usdt_wallet->amount - $usdt_freeze_amount) < $total_tether) {

                            // Return tether that's need
                            $required_amount_tether = $total_tether - $user_usdt_wallet->amount;
                            $data = [
                                'required_amount_tether' => $required_amount_tether
                            ];

                            return Response::failed(__('trade_exchange_failed_tether_are_needed'), $data, 422, -2);


                        } else {


                            $order_setting = OrderSetting::checkOrderMode();
                            $coinex_order_result = 0;
                            if ($order_setting->mode == OrderSetting::AUTOMATIC_MODE) {
                                $coinex_order_info = [
                                    'access_id' => config('bitazar.coinex.access_id'),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => 'buy',
                                    'amount' => number_format($pair_amount, $trading_decimal),
                                    'tonce' => now()->toDateTimeString(),
                                    'account_id' => 0,
                                    'client_id' => @Auth::guard('api')->user()->mobile
                                ];


                                $coinex_order_result = BuyExchangeTrait::coinexBuyOrder($coinex_order_info, 'market');

                            } else {

                                $order_info = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'time' => now(),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => OrdersList::MARKET_TYPE,
                                    'role' => OrdersList::BUY_ROLE,
                                    'order_price' => $total_tether,
                                    'avg_price' => 0,
                                    'amount' => $order_amount,
                                    'total_price' => $toman_amount,
                                    'current_wage' => $wage,
                                    'toman_wage' => $tether_price * $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::PENDING_STATUS,
                                ];

                                OrderRepository::storeBuyOrders($order_info);
                            }

                            if (!@$coinex_order_result->code) {

                                // When user have usdt to buy currency
                                $this->userWalletsRepository
                                    ->decreaseWalletAmount(Auth::guard('api')->id(), 'USDT', $total_tether);
                                $this->userWalletsRepository
                                    ->increaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $order_amount);

                                $order_info = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'time' => now(),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => OrdersList::MARKET_TYPE,
                                    'role' => OrdersList::BUY_ROLE,
                                    'order_price' => $total_tether,
                                    'avg_price' => 0,
                                    'amount' => $order_amount,
                                    'total_price' => $toman_amount,
                                    'current_wage' => $wage,
                                    'toman_wage' => $tether_price * $wage,
                                    'filled' => 100,
                                    'status' => OrdersList::DONE_STATUS,
                                ];

                                $order_info = OrderRepository::storeBuyOrders($order_info);

                                // Prepare coinex done order information (order_id is foreign key on orders_list table)
                                $done_order_info = [
                                    'order_id' => $order_info->id,
                                    'amount' => @$coinex_order_result->amount,
                                    'asset_fee' => @$coinex_order_result->asset_fee,
                                    'avg_price' => @$coinex_order_result->avg_price,
                                    'client_id' => @$coinex_order_result->client_id,
                                    'create_time' => @$coinex_order_result->create_time,
                                    'deal_amount' => @$coinex_order_result->deal_amount,
                                    'deal_fee' => @$coinex_order_result->deal_fee,
                                    'deal_money' => @$coinex_order_result->deal_money,
                                    'fee_asset' => @$coinex_order_result->fee_asset,
                                    'fee_discount' => @$coinex_order_result->fee_discount,
                                    'finished_time' => @$coinex_order_result->finished_time,
                                    'identifier' => @$coinex_order_result->id,
                                    'left' => @$coinex_order_result->left,
                                    'maker_fee_rate' => @$coinex_order_result->maker_fee_rate,
                                    'market' => @$coinex_order_result->market,
                                    'money_fee' => @$coinex_order_result->money_fee,
                                    'order_type' => @$coinex_order_result->order_type,
                                    'price' => @$coinex_order_result->price,
                                    'status' => @$coinex_order_result->status,
                                    'stock_fee' => @$coinex_order_result->stock_fee,
                                    'taker_fee_rate' => @$coinex_order_result->taker_fee_rate,
                                    'type' => @$coinex_order_result->type
                                ];

                                // Store done order
                                DoneOrdersList::store($done_order_info);

                                // Store daily income
                                $quantity = $tether_price * $wage;
                                Income::store($quantity);

                                $income_log = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'order_id' => $order_info->id,
                                    'wage' => $quantity,
                                    'type' => OrdersList::BUY_ROLE,
                                ];
                                IncomeLog::store($income_log);

                                //event(new OrderVolumenScore(Auth::guard('api')->user()));

                                $currency = ExchangeList::findBySymbol(strtoupper($request->pair_type));
                                event(new pay_commision(Auth::guard('api')->user(), ($static_percent / 100) * $order_amount, $currency->id));

                                // Send message of user order
                                SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['market_order'], [number_format($pair_amount, $trading_decimal), $request->pair_type]);

                                return Response::success(__('trade_exchange_success_wallet'), null, 200);

                            } else {

                                $order_log = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => OrdersList::MARKET_TYPE,
                                    'role' => OrdersList::BUY_ROLE,
                                    'order_price' => $total_tether,
                                    'avg_price' => 0,
                                    'amount' => $order_amount,
                                    'total_price' => $toman_amount,
                                    'current_wage' => $wage,
                                    'toman_wage' => $tether_price * $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::CANCEL_STATUS,
                                    'request_values' => json_encode($coinex_order_info),
                                    'exchange_error' => $coinex_order_result->error
                                ];

                                OrdersLog::storeLog($order_log);

                                SmsHelper::sendMessage('09122335645', $this->templates_id['coinex_error'], [Auth::guard('api')->user()->mobile . '' . OrdersList::MARKET_TYPE ,$coinex_order_result->error]);

                                return Response::failed(__('trade_exchange_failed_problem_about_operation'), null, 400, -3);
                            }
                            // End user buy currenct with usdt

                        }

                    // When user select toman wallet
                    if ($request->irr_wallet == true)
                        if (($user_toman_wallet->amount - $irr_freeze_amount) < $toman_amount) {

                            // Return toman that's need
                            $required_amount_toman = $toman_amount - $user_toman_wallet->amount;
                            $data = [
                                'required_amount_tether' => $required_amount_toman
                            ];

                            return Response::failed(__('buy_exchange_failed_toman_are_needed'), $data, 422, -2);


                        } else {

                            $order_setting = OrderSetting::checkOrderMode();
                            $coinex_order_result = 0;
                            if ($order_setting->mode == OrderSetting::AUTOMATIC_MODE) {
                                $coinex_order_info = [
                                    'access_id' => config('bitazar.coinex.access_id'),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => 'buy',
                                    'amount' => number_format($pair_amount, $trading_decimal),
                                    'tonce' => now()->toDateTimeString(),
                                    'account_id' => 0,
                                    'client_id' => @Auth::guard('api')->user()->mobile
                                ];

                                $coinex_order_result = BuyExchangeTrait::coinexBuyOrder($coinex_order_info, 'market');

                            }
                            if (!@$coinex_order_result->code) {

                                // When user have toman to buy currency
                                $this->userWalletsRepository
                                    ->decreaseWalletAmount(Auth::guard('api')->id(), 'IRR', $total_tether * $tether_price);
                                $this->userWalletsRepository
                                    ->increaseWalletAmount(Auth::guard('api')->id(), strtoupper($request->pair_type), $order_amount);

                                $order_info = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'time' => now(),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => OrdersList::MARKET_TYPE,
                                    'role' => OrdersList::BUY_ROLE,
                                    'order_price' => $total_tether,
                                    'avg_price' => 0,
                                    'amount' => $order_amount,
                                    'total_price' => $toman_amount,
                                    'current_wage' => $wage,
                                    'toman_wage' => $tether_price * $wage,
                                    'filled' => 100,
                                    'status' => OrdersList::DONE_STATUS,
                                ];

                                $order_info = OrderRepository::storeBuyOrders($order_info);

                                // Prepare coinex done order information (order_id is foreign key on orders_list table)
                                $done_order_info = [
                                    'order_id' => $order_info->id,
                                    'amount' => @$coinex_order_result->amount,
                                    'asset_fee' => @$coinex_order_result->asset_fee,
                                    'avg_price' => @$coinex_order_result->avg_price,
                                    'client_id' => @$coinex_order_result->client_id,
                                    'create_time' => @$coinex_order_result->create_time,
                                    'deal_amount' => @$coinex_order_result->deal_amount,
                                    'deal_fee' => @$coinex_order_result->deal_fee,
                                    'deal_money' => @$coinex_order_result->deal_money,
                                    'fee_asset' => @$coinex_order_result->fee_asset,
                                    'fee_discount' => @$coinex_order_result->fee_discount,
                                    'finished_time' => @$coinex_order_result->finished_time,
                                    'identifier' => @$coinex_order_result->id,
                                    'left' => @$coinex_order_result->left,
                                    'maker_fee_rate' => @$coinex_order_result->maker_fee_rate,
                                    'market' => @$coinex_order_result->market,
                                    'money_fee' => @$coinex_order_result->money_fee,
                                    'order_type' => @$coinex_order_result->order_type,
                                    'price' => @$coinex_order_result->price,
                                    'status' => @$coinex_order_result->status,
                                    'stock_fee' => @$coinex_order_result->stock_fee,
                                    'taker_fee_rate' => @$coinex_order_result->taker_fee_rate,
                                    'type' => @$coinex_order_result->type
                                ];

                                // Store done order
                                DoneOrdersList::store($done_order_info);

                                // Store daily income
                                $quantity = $tether_price * $wage;
                                Income::store($quantity);

                                $income_log = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'order_id' => $order_info->id,
                                    'wage' => $quantity,
                                    'type' => OrdersList::BUY_ROLE,
                                ];
                                IncomeLog::store($income_log);

                                //event(new OrderVolumenScore(Auth::guard('api')->user()));

                                $currency = ExchangeList::findBySymbol(strtoupper($request->pair_type));
                                event(new pay_commision(Auth::guard('api')->user(), $order_amount, $currency->id));

                                // Send message of user order
                                SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['market_order'], [number_format($pair_amount, $trading_decimal), $request->pair_type]);

                                return Response::success(__('trade_exchange_success_wallet'), null, 200);

                            } else {

                                $order_log = [
                                    'user_id' => Auth::guard('api')->id(),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => OrdersList::MARKET_TYPE,
                                    'role' => OrdersList::BUY_ROLE,
                                    'order_price' => $toman_amount,
                                    'avg_price' => 0,
                                    'amount' => $order_amount,
                                    'total_price' => $total_tether,
                                    'current_wage' => $wage,
                                    'toman_wage' => $tether_price * $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::CANCEL_STATUS,
                                    'request_values' => json_encode($coinex_order_info),
                                    'exchange_error' => $coinex_order_result->error
                                ];
                                OrdersLog::storeLog($order_log);

                                SmsHelper::sendMessage('09122335645', $this->templates_id['coinex_error'], [Auth::guard('api')->user()->mobile . '' . OrdersList::MARKET_TYPE ,$coinex_order_result->error]);

                                return Response::failed(__('trade_exchange_failed_problem_about_operation'), null, 400, -3);
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
                        $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tether_price);

                        $pricing_decimal = $coinex_market_info->pricing_decimal;
                        $trading_decimal = $coinex_market_info->trading_decimal;

                        $invoice = [
                            'your_payment' => $toman_amount,
                            'wage_percent' => $wage,
                            'currency_type_wage_percent' => 'تتر',
                            'your_receipt' => $request->pair_amount,
                            'currency_type_your_receipt' => strtoupper($request->pair_type),
                            'currency_type' => 'تومان'
                        ];

                        if ($request->request_type == 'invoice') {

                            return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);

                        } elseif ($request->request_type == 'buy') {

                            $irr_freeze_amount = FreezeHelper::getFreezeAmount('IRR');
                            $usdt_freeze_amount = FreezeHelper::getFreezeAmount('USDT');

                            $user_usdt_wallet = $this->userWalletsRepository
                                ->getUserWallet(Auth::guard('api')->id(), 'USDT');

                            $user_toman_wallet = $this->userWalletsRepository
                                ->getUserWallet(Auth::guard('api')->id(), 'IRR');

                            if (($user_usdt_wallet->amount - $usdt_freeze_amount) < $total_tether) {

                                // Check toman wallet when user havent credit
                                if (($user_toman_wallet->amount - $irr_freeze_amount) < ($total_tether * $tether_price)) {
                                    $required_amount_tether = $total_tether - $user_usdt_wallet->amount;
                                    $data = [
                                        'required_amount_tether' => $required_amount_tether
                                    ];

                                    return Response::failed(__('trade_exchange_failed_tether_are_needed'), $data, 422, -2);

                                } else {
                                    $this->userWalletsRepository
                                        ->decreaseWalletAmount(Auth::guard('api')->id(), 'IRR', $total_tether * $tether_price);
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
                                        'toman_wage' => $tether_price * $wage,
                                        'filled' => 0,
                                        'status' => OrdersList::PENDING_STATUS,
                                    ];
                                    OrderRepository::storeBuyOrders($order_info);


                                    return Response::success(__('trade_exchange_success_wallet'), null, 200);

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
                                    'toman_wage' => $tether_price * $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::PENDING_STATUS,
                                ];

                                OrderRepository::storeBuyOrders($order_info);

                                $order_setting = OrderSetting::checkOrderMode();
                                if ($order_setting->mode == OrderSetting::AUTOMATIC_MODE) {
                                    $coinex_order_info = [
                                        'access_id' => config('bitazar.coinex.access_id'),
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => 'buy',
                                        'amount' => $request->pair_amount,
                                        'tonce' => now()->toDateTimeString(),
                                        'account_id' => 0,
                                        'client_id' => @Auth::guard('api')->user()->mobile
                                    ];

                                    BuyExchangeTrait::coinexBuyOrder($coinex_order_info, 'market');
                                }

                                // Send message of user order
                                SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['market_order'], [$request->pair_amount, $request->pair_type]);

                                return Response::success(__('trade_exchange_success_wallet'), null, 200);


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
                        $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tether_price);

                        $pricing_decimal = $coinex_market_info->pricing_decimal;
                        $trading_decimal = $coinex_market_info->trading_decimal;

                        $invoice = [
                            'your_payment' => $toman_amount,
                            'wage_percent' => $wage,
                            'currency_type_wage_percent' => 'تتر',
                            'your_receipt' => $request->pair_amount,
                            'currency_type_your_receipt' => strtoupper($request->pair_type),
                            'currency_type' => 'تومان'
                        ];

                        if ($request->request_type == 'invoice') {

                            return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);

                        } elseif ($request->request_type == 'buy') {

                            $irr_freeze_amount = FreezeHelper::getFreezeAmount('IRR');
                            $usdt_freeze_amount = FreezeHelper::getFreezeAmount('USDT');

                            $user_usdt_wallet = $this->userWalletsRepository
                                ->getUserWallet(Auth::guard('api')->id(), 'USDT');

                            $user_toman_wallet = $this->userWalletsRepository
                                ->getUserWallet(Auth::guard('api')->id(), 'IRR');

                            if (($user_usdt_wallet->amount - $usdt_freeze_amount) < $total_tether) {

                                // Check toman wallet when user havent credit
                                if (($user_toman_wallet->amount - $irr_freeze_amount) < ($total_tether * $tether_price)) {
                                    $required_amount_tether = $total_tether - $user_usdt_wallet->amount;
                                    $data = [
                                        'required_amount_tether' => $required_amount_tether
                                    ];

                                    return Response::failed(__('trade_exchange_failed_tether_are_needed'), $data, 422, -2);

                                } else {
                                    $this->userWalletsRepository
                                        ->decreaseWalletAmount(Auth::guard('api')->id(), 'IRR', $total_tether * $tether_price);
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
                                        'toman_wage' => $tether_price * $wage,
                                        'filled' => 0,
                                        'status' => OrdersList::PENDING_STATUS,
                                    ];
                                    OrderRepository::storeBuyOrders($order_info);

                                    // Send message of user order
                                    SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['market_order'], [$request->pair_amount, $request->pair_type]);


                                    return Response::success(__('trade_exchange_success_wallet'), null, 200);

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
                                    'toman_wage' => $tether_price * $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::PENDING_STATUS,
                                ];

                                OrderRepository::storeBuyOrders($order_info);

                                $order_setting = OrderSetting::checkOrderMode();
                                if ($order_setting->mode == OrderSetting::AUTOMATIC_MODE) {
                                    $coinex_order_info = [
                                        'access_id' => config('bitazar.coinex.access_id'),
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => 'buy',
                                        'amount' => $request->pair_amount,
                                        'tonce' => now()->toDateTimeString(),
                                        'account_id' => 0,
                                        'client_id' => @Auth::guard('api')->user()->mobile
                                    ];

                                    BuyExchangeTrait::coinexBuyOrder($coinex_order_info, 'market');
                                }

                                return Response::success(__('buy_exchange_success_wallet'), null, 200);


                            }
                        }
                    }
                }
                break;
        }

    }

    public function limit(Request $request)
    {

        $santizier = new Sanitizer($request->all(), [
            'pair_type' => 'strip_tags',
            'type' => 'strip_tags',
            'pair_price' => 'strip_tags',
            'pair_amount' => 'strip_tags',
            'request_type' => 'strip_tags',
            'irr_wallet' => 'strip_tags',
            'usdt_wallet' => 'strip_tags'
        ]);

        $request_sanitized = $santizier->sanitize();

        $validator = Validator::make($request_sanitized, [
            'pair_type' => ['string'],
            'type' => ['string'],
            'pair_price' => ['numeric'],
            'pair_amount' => ['numeric'],
            'request_type' => ['string']
        ]);

        if ($validator->fails()) {
            return Response::failed($validator->errors()->toArray(), null, 422, -1);
        }

        $tether_price = UsdtPrice::get()->quantity / 10;
        $static_percent = \config('bitazar.coinex.wage_percent');


        $exchange_setting = ExchangeSetting::getExchangeSettings();
        switch ($exchange_setting->exchange) {
            case 'کوینکس':
            default:
                // Get coinex market info for find sepcific currency and min amount and taker fee rate
                $coinex_market_info = CoinexMarketInfoExtracted::findMarketInfo(strtoupper($request->pair_type) . 'USDT');
                $coinex_market_info = json_decode($coinex_market_info->data);
                $min_amount = $coinex_market_info->min_amount;
                $taker_fee_rate = \config('bitazar.coinex.network_wage_percent');

                $pricing_decimal = $coinex_market_info->pricing_decimal;
                $trading_decimal = $coinex_market_info->trading_decimal;

                $invoice = [];
                $wage = BuyExchangeTrait::calculateWage($request->pair_amount, $request->pair_price, $static_percent);
                $total_tether = BuyExchangeTrait::calculateTotalTether($request->pair_amount, $request->pair_price, $taker_fee_rate, $static_percent);
                $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tether_price);
                $taker_fee_rate = BuyExchangeTrait::calculateNetworkWage($request->pair_amount, $taker_fee_rate, $request->pair_price);

                $invoice = [
                    'your_payment' => $total_tether,
                    'wage_percent' => $wage,
                    'network_wage' => $taker_fee_rate,
                    'currency_type_wage_percent' => 'تتر',
                    'your_receipt' => $request->pair_amount,
                    'currency_type_your_receipt' => $request->pair_type,
                    'currency_type' => 'تتر',
                    'payment_type' => 'wallet'
                ];

                if ($request->request_type == 'invoice') {

                    return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);

                } elseif ($request->request_type == 'buy') {

                    // Check user amount with minimum amount of order
                    if ($request->pair_amount < $min_amount) {
                        $data = [
                            'min_required_pair_amount' => $min_amount,
                            'your_pair_amount' => $request->pair_amount,
                            'difference_in_min_amount' => $min_amount - $request->pair_amount
                        ];

                        return Response::failed(__('trade_exchange_failed_minimum_order'), $data, 200, -4);

                    }

                    $usdt_freeze_amount = FreezeHelper::getFreezeAmount('USDT');

                    $user_usdt_wallet = $this->userWalletsRepository
                        ->getUserWallet(Auth::guard('api')->id(), 'USDT');

                    if (($user_usdt_wallet->amount - $usdt_freeze_amount) < $total_tether) {

                        $required_amount_tether = $total_tether - ($user_usdt_wallet->amount - $usdt_freeze_amount);
                        $data = [
                            'required_amount_tether' => $required_amount_tether
                        ];

                        return Response::failed(__('trade_exchange_failed_tether_are_needed'), $data, 422, -2);

                    } else {

                        $order_setting = OrderSetting::checkOrderMode();
                        if ($order_setting->mode == OrderSetting::AUTOMATIC_MODE) {
                            $coinex_order_info = [
                                'access_id' => config('bitazar.coinex.access_id'),
                                'market' => strtoupper($request->pair_type) . 'USDT',
                                'type' => 'buy',
                                'amount' => number_format($request->pair_amount, $trading_decimal),
                                'price' => number_format($request->pair_price, $pricing_decimal),
                                'tonce' => now()->toDateTimeString(),
                                'account_id' => 0,
                                'client_id' => @Auth::guard('api')->user()->mobile
                            ];


                            $coinex_order_result = BuyExchangeTrait::coinexBuyOrder($coinex_order_info, 'limit');
                        }


                        if (!@$coinex_order_result->code) {


                            $order_info = [
                                'user_id' => Auth::guard('api')->id(),
                                'time' => now(),
                                'market' => strtoupper($request->pair_type) . 'USDT',
                                'type' => OrdersList::LIMIT_TYPE,
                                'role' => OrdersList::BUY_ROLE,
                                'order_price' => $total_tether,
                                'avg_price' => $total_tether,
                                'amount' => $request->pair_amount,
                                'total_price' => $toman_amount,
                                'current_wage' => $wage,
                                'toman_wage' => $tether_price * $wage,
                                'filled' => 0,
                                'status' => OrdersList::PENDING_STATUS
                            ];

                            $order_info = OrderRepository::storeBuyOrders($order_info);

                            // Prepare coinex done order information (order_id is foreign key on orders_list table)
                            $done_order_info = [
                                'order_id' => $order_info->id,
                                'amount' => @$coinex_order_result->amount,
                                'asset_fee' => @$coinex_order_result->asset_fee,
                                'avg_price' => @$coinex_order_result->avg_price,
                                'client_id' => @$coinex_order_result->client_id,
                                'create_time' => @$coinex_order_result->create_time,
                                'deal_amount' => @$coinex_order_result->deal_amount,
                                'deal_fee' => @$coinex_order_result->deal_fee,
                                'deal_money' => @$coinex_order_result->deal_money,
                                'fee_asset' => @$coinex_order_result->fee_asset,
                                'fee_discount' => @$coinex_order_result->fee_discount,
                                'finished_time' => @$coinex_order_result->finished_time,
                                'identifier' => @$coinex_order_result->id,
                                'left' => @$coinex_order_result->left,
                                'maker_fee_rate' => @$coinex_order_result->maker_fee_rate,
                                'market' => @$coinex_order_result->market,
                                'money_fee' => @$coinex_order_result->money_fee,
                                'order_type' => @$coinex_order_result->order_type,
                                'price' => @$coinex_order_result->price,
                                'status' => @$coinex_order_result->status,
                                'stock_fee' => @$coinex_order_result->stock_fee,
                                'taker_fee_rate' => @$coinex_order_result->taker_fee_rate,
                                'type' => @$coinex_order_result->type
                            ];

                            // Store done order
                            DoneOrdersList::store($done_order_info);


                            // Send message of user order
                            SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['limit_order'], [$request->pair_amount, $request->pair_type]);

                            return Response::success(__('trade_exchange_success_order'), null, 200);

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
                                'toman_wage' => $tether_price * $wage,
                                'filled' => 0,
                                'status' => OrdersList::CANCEL_STATUS,
                                'request_values' => json_encode($coinex_order_info),
                                'exchange_error' => $coinex_order_result->error
                            ];

                            OrdersLog::storeLog($order_log);

                            SmsHelper::sendMessage('09122335645', $this->templates_id['coinex_error'], [Auth::guard('api')->user()->mobile . '' . OrdersList::MARKET_TYPE ,$coinex_order_result->error]);

                            return Response::failed(__('trade_exchange_failed_problem_about_operation'), null, 400, -3);
                        }

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
                        $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tether_price);
                        $invoice = [
                            'your_payment' => $total_tether,
                            'wage_percent' => $wage,
                            'currency_type_wage_percent' => 'تتر',
                            'your_receipt' => $request->pair_amount,
                            'currency_type_your_receipt' => $request->pair_type,
                            'currency_type' => 'تتر'
                        ];

                        if ($request->request_type == 'invoice') {

                            return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);

                        } elseif ($request->request_type == 'buy') {
                            $order_info = [
                                'user_id' => Auth::guard('api')->id(),
                                'time' => now(),
                                'market' => strtoupper($request->pair_type) . 'USDT',
                                'type' => OrdersList::LIMIT_TYPE,
                                'role' => OrdersList::BUY_ROLE,
                                'order_price' => $total_tether,
                                'avg_price' => $toman_amount,
                                'amount' => $request->pair_amount,
                                'total_price' => $toman_amount,
                                'current_wage' => $wage,
                                'toman_wage' => $tether_price * $wage,
                                'filled' => 0,
                                'status' => OrdersList::PENDING_STATUS
                            ];

                            OrderRepository::storeBuyOrders($order_info);

                            $order_setting = OrderSetting::checkOrderMode();
                            if ($order_setting->mode == OrderSetting::AUTOMATIC_MODE) {
                                $coinex_order_info = [
                                    'access_id' => config('bitazar.coinex.access_id'),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => 'buy',
                                    'amount' => $request->pair_amount,
                                    'tonce' => now()->toDateTimeString(),
                                    'account_id' => 0,
                                    'client_id' => @Auth::guard('api')->user()->mobile
                                ];

                                BuyExchangeTrait::coinexBuyOrder($coinex_order_info, 'limit');
                            }


                            return Response::success(__('trade_exchange_success_order'), null, 200);


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
                        $toman_amount = BuyExchangeTrait::calculateTomanAmount($total_tether, $tether_price);
                        $invoice = [
                            'your_payment' => $total_tether,
                            'wage_percent' => $wage,
                            'currency_type_wage_percent' => 'تتر',
                            'your_receipt' => $request->pair_amount,
                            'currency_type_your_receipt' => $request->pair_type,
                            'currency_type' => 'تتر'
                        ];

                        if ($request->request_type == 'invoice') {

                            return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);

                        } elseif ($request->request_type == 'buy') {
                            $order_info = [
                                'user_id' => Auth::guard('api')->id(),
                                'time' => now(),
                                'market' => strtoupper($request->pair_type) . 'USDT',
                                'type' => OrdersList::LIMIT_TYPE,
                                'role' => OrdersList::BUY_ROLE,
                                'order_price' => $total_tether,
                                'avg_price' => $toman_amount,
                                'amount' => $request->pair_amount,
                                'total_price' => $toman_amount,
                                'current_wage' => $wage,
                                'toman_wage' => $tether_price * $wage,
                                'filled' => 0,
                                'status' => OrdersList::PENDING_STATUS
                            ];

                            OrderRepository::storeBuyOrders($order_info);

                            $order_setting = OrderSetting::checkOrderMode();
                            if ($order_setting->mode == OrderSetting::AUTOMATIC_MODE) {
                                $coinex_order_info = [
                                    'access_id' => config('bitazar.coinex.access_id'),
                                    'market' => strtoupper($request->pair_type) . 'USDT',
                                    'type' => 'buy',
                                    'amount' => $request->pair_amount,
                                    'tonce' => now()->toDateTimeString(),
                                    'account_id' => 0,
                                    'client_id' => @Auth::guard('api')->user()->mobile
                                ];

                                BuyExchangeTrait::coinexBuyOrder($coinex_order_info, 'limit');
                            }


                            return Response::success(__('trade_exchange_success_order'), null, 200);


                        }
                    }
                }
                break;

//          end limit invoice or buy exchanges
        }

    }

}
