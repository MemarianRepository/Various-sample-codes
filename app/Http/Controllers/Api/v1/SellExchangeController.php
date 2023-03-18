<?php

namespace App\Http\Controllers\Api\v1;

use App\Events\OrderVolumenScore;
use App\Events\pay_commision;
use App\Helpers\FreezeHelper;
use App\Helpers\RequestHelper;
use App\Helpers\SmsHelper;
use App\Http\Controllers\Controller;
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
use App\Models\Currency;
use App\Models\OrderSetting;
use App\Repositories\Api\v1\OrderRepository;
use App\Repositories\Api\v1\UserWalletsRepository;
use Illuminate\Http\Request;
use App\Traits\SellExchangeTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Elegant\Sanitizer\Sanitizer;
use Illuminate\Support\Facades\Validator;
use Lin\Coinex\CoinexExchange;

/**
 * @group Trade section
 * Api to buy and sell currency
 **/
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

    public function market(Request $request)
    {

        $santizier = new Sanitizer($request->all(), [
            'pair_type' => 'strip_tags',
            'type' => 'strip_tags',
            'pair_price' => 'strip_tags',
            'pair_amount' => 'strip_tags',
            'request_type' => 'strip_tags',
            'receipt_type' => 'strip_tags'
        ]);

        $request_sanitized = $santizier->sanitize();

        $validator = Validator::make($request_sanitized, [
            'pair_type' => ['string'],
            'type' => ['string'],
            'pair_price' => ['numeric'],
            'pair_amount' => ['numeric'],
            'request_type' => ['string'],
            'receipt_type' => ['string']
        ]);

        if ($validator->fails()) {
            return Response::failed($validator->errors()->toArray(), null, 422, -1);
        }

        $tether_price = UsdtPrice::get()->quantity / 10;
        $static_percent = \config('bitazar.coinex.wage_percent');

        //      Market invoice or sell tether
        if ($request->pair_type == 'USDT' || $request->pair_type == 'usdt') {
            $total_price = $request->pair_amount * $tether_price;
            $data = [
                'your_payment' => $request->pair_amount,
                'wage_percent' => 0,
                'currency_type_wage_percent' => 'تومان',
                'your_receipt' => $total_price,
                'currency_type_your_receipt' => 'تومان',
                'currency_type' => 'تتر',
                'payment_type' => 'wallet'
            ];
            if($request->request_type == 'invoice') {

                return Response::success(__('sell_exchange_success_toman_calculated'), $data, 200);

            } elseif($request->request_type == 'sell'){

                $usdt_freeze_amount = FreezeHelper::getFreezeAmount('USDT');

                $user_wallet = $this->userWalletsRepository
                    ->getUserWallet(Auth::guard('api')->id(), 'USDT');

                if(($user_wallet->amount - $usdt_freeze_amount) < $request->pair_amount){
                    $required_tether_amount  =  $request->pair_amount - ($user_wallet->amount - $usdt_freeze_amount);
                    $data = [
                        'required_tether_amount' => $required_tether_amount
                    ];

                    return Response::failed(__('trade_exchange_failed_tether_are_needed'), $data, 422, -1);


                }
                else{
                    $this->userWalletsRepository
                        ->decreaseWalletAmount(Auth::guard('api')->id(), 'USDT', $request->pair_amount);
                    $this->userWalletsRepository
                        ->increaseWalletAmount(Auth::guard('api')->id(), 'IRR', $total_price);


                    return Response::success(__('trade_exchange_success_toman_charged'), null, 200);


                }
            }
        }
//      End market invoice or sell tether

//      Market invoice or sell exchanges
        $exchange_setting = ExchangeSetting::getExchangeSettings();
        switch ($exchange_setting->exchange) {
            case 'کوینکس':
            default:
                // Get coinex market info for find sepcific currency and min amount and taker fee rate
                $coinex_market_info = CoinexMarketInfoExtracted::findMarketInfo(strtoupper($request->pair_type) . 'USDT');
                $coinex_market_info = json_decode($coinex_market_info->data);
                $min_amount = $coinex_market_info->min_amount;
                $maker_fee_rate = \config('bitazar.coinex.network_wage_percent');

                $pricing_decimal = $coinex_market_info->pricing_decimal;
                $trading_decimal = $coinex_market_info->trading_decimal;

                // Request to get ticker from coinex
                $params = [
                    'market' => strtoupper($request->pair_type) . 'USDT'
                ];

                $result = RequestHelper::send('https://api.coinex.com/v1/market/ticker', 'get', $params, null);

                $info = (object) $result->data->ticker;

                $invoice = [];

                $wage = SellExchangeTrait::calculateWage($request->pair_amount, $info->last, $static_percent);
                $total_tether = SellExchangeTrait::calculateTotalTether($request->pair_amount, $info->last, $maker_fee_rate, $static_percent);
                $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tether_price);
                $maker_fee_rate = SellExchangeTrait::calculateNetworkWage($request->pair_amount, $maker_fee_rate, $info->last);

                switch ($request->receipt_type) {
                    case 'usdt':
                    default:
                        $invoice = [
                            'your_payment' => $request->pair_amount,
                            'wage_percent' => $wage,
                            'network_wage' => $maker_fee_rate,
                            'currency_type_wage_percent' => 'تتر',
                            'your_receipt' => $total_tether,
                            'currency_type_your_receipt' => 'تتر',
                            'currency_type' => strtoupper($request->pair_type),
                            'payment_type' => 'wallet'
                        ];
                        break;
                    case 'toman':
                        $invoice = [
                            'your_payment' => $request->pair_amount,
                            'wage_percent' => $wage * $tether_price,
                            'network_wage' => $maker_fee_rate * $tether_price,
                            'currency_type_wage_percent' => 'تومان',
                            'your_receipt' => $toman_amount,
                            'currency_type_your_receipt' => 'تومان',
                            'currency_type' => strtoupper($request->pair_type),
                            'payment_type' => 'wallet'
                        ];
                        break;
                }


                if ($request->request_type == 'invoice') {


                    return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);

                } elseif ($request->request_type == 'sell') {

                    // Check user amount with minimum amount of order
                    if ($request->pair_amount < $min_amount) {
                        $data = [
                            'min_required_pair_amount' => $min_amount,
                            'your_pair_amount' => $request->pair_amount,
                            'difference_in_min_amount' => $min_amount - $request->pair_amount
                        ];

                        return Response::failed(__('trade_exchange_failed_minimum_order'), $data, 200, -4);

                    }

                    $pair_freeze_amount = FreezeHelper::getFreezeAmount(strtoupper($request->pair_type));

                    // Get user wallet (that's have currency)
                    $user_wallet = $this->userWalletsRepository
                        ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));

                    // Check amount of wallet
                    if (($user_wallet->amount - $pair_freeze_amount) < $request->pair_amount) {

                        $required_exchange_amount = $request->pair_amount - ($user_wallet->amount - $pair_freeze_amount);
                        $data = [
                            'required_exchange_amount' => $required_exchange_amount
                        ];

                        return Response::failed(__('sell_exchange_failed_more_currency_needed'), $data, 422, -2);


                    } else {

                        $order_setting = OrderSetting::checkOrderMode();
                        $coinex_order_result = 0;
                        if ($order_setting->mode == OrderSetting::AUTOMATIC_MODE) {

                            // Prepare send request to coinex
                            $coinex_order_info = [
                                'access_id' => config('bitazar.coinex.access_id'),
                                'market' => strtoupper($request->pair_type) . 'USDT',
                                'type' => 'sell',
                                'amount' => number_format($request->pair_amount, $trading_decimal),
                                'tonce' => now()->toDateTimeString(),
                                'account_id' => 0,
                                'client_id' => Auth::guard('api')->user()->mobile
                            ];

                            // Send request to coinex
                            $coinex_order_result = SellExchangeTrait::SellOrderCoinex($coinex_order_info, 'market');

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
                                'toman_wage' => $tether_price * $wage,
                                'filled' => 0,
                                'status' => OrdersList::PENDING_STATUS,
                            ];
                            OrderRepository::storeSellOrders($order_info);
                        }

                        if (!@$coinex_order_result->code) {

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
                                'toman_wage' => $tether_price * $wage,
                                'filled' => 100,
                                'status' => OrdersList::DONE_STATUS,
                            ];
                            $order_info = OrderRepository::storeSellOrders($order_info);

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
                                'type' => OrdersList::SELL_ROLE,
                            ];
                            IncomeLog::store($income_log);

                            //event(new OrderVolumenScore(Auth::guard('api')->user()));

                            $currency = ExchangeList::findBySymbol(strtoupper($request->pair_type));
                            event(new pay_commision(Auth::guard('api')->user(), $wage, $currency->id));

                            // Send message of user order
                            SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['sell_market_order'], [$request->pair_amount, $request->pair_type]);


                            return Response::success(__('sell_exchange_success_sold'), null, 200);


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
                        $maker_fee_rate = $market_info->makerFeeRate;
                        $invoice = [];
                        $wage = SellExchangeTrait::calculateWage($request->pair_amount, $market_info->last, $static_percent);
                        $total_tether = SellExchangeTrait::calculateTotalTether($request->pair_amount, $market_info->last, $maker_fee_rate, $static_percent);
                        $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tether_price);

                        $pricing_decimal = $coinex_market_info->pricing_decimal;
                        $trading_decimal = $coinex_market_info->trading_decimal;

                        switch ($request->receipt_type) {
                            case 'usdt':
                            default:
                                $invoice = [
                                    'your_payment' => $request->pair_amount,
                                    'wage_percent' => $wage,
                                    'currency_type_wage_percent' => 'تتر',
                                    'your_receipt' => $total_tether,
                                    'currency_type_your_receipt' => 'تتر',
                                    'currency_type' => strtoupper($request->pair_type)
                                ];
                                break;
                            case 'toman':
                                $invoice = [
                                    'your_payment' => $request->pair_amount,
                                    'wage_percent' => $wage,
                                    'currency_type_wage_percent' => 'تتر',
                                    'your_receipt' => $toman_amount,
                                    'currency_type_your_receipt' => 'تومان',
                                    'currency_type' => strtoupper($request->pair_type)
                                ];
                                break;
                        }

                        if ($request->request_type == 'invoice') {

                            return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);

                        } elseif ($request->request_type == 'sell') {

                            $user_wallet = $this->userWalletsRepository
                                ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));

                            if ($user_wallet->amount < $request->pair_amount) {
                                $required_exchange_amount = $request->pair_amount - $user_wallet->amount;
                                $data = [
                                    'required_exchange_amount' => $required_exchange_amount
                                ];


                                return Response::failed(__('sell_exchange_failed_more_currency_needed'), $data, 422, -2);


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
                                if ($order_setting->mode == OrderSetting::AUTOMATIC_MODE) {
                                    $coinex_order_info = [
                                        'access_id' => config('bitazar.coinex.access_id'),
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => 'sell',
                                        'amount' => number_format($request->pair_amount, $trading_decimal),
                                        'tonce' => now()->toDateTimeString(),
                                        'account_id' => 0,
                                        'client_id' => Auth::guard('api')->user()->mobile
                                    ];

                                    $coinex_order_result = SellExchangeTrait::SellOrderCoinex($coinex_order_info, 'market');
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
                                        'id' => @$coinex_order_result->id,
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

                                    //event(new OrderVolumenScore(Auth::guard('api')->user()));

                                    $currency = ExchangeList::findBySymbol(strtoupper($request->pair_type));
                                    event(new pay_commision(Auth::guard('api')->user(), $wage, $currency->id));

                                    // Send message of user order
                                    SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['sell_limit_order'], [$request->pair_amount, $request->pair_type]);


                                    return Response::success(__('trade_exchange_success_wallet'), null, 200);

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
                                        'exchange_error' => $coinex_order_result->error
                                    ];

                                    OrdersLog::storeLog($order_log);

                                    SmsHelper::sendMessage('09122335645', $this->templates_id['coinex_error'], [Auth::guard('api')->user()->mobile . '' . OrdersList::MARKET_TYPE ,$coinex_order_result->error]);

                                    return Response::failed(__('trade_exchange_failed_problem_about_operation'), null, 400, -3);
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
                        $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tether_price);

                        $pricing_decimal = $coinex_market_info->pricing_decimal;
                        $trading_decimal = $coinex_market_info->trading_decimal;

                        switch ($request->receipt_type) {
                            case 'usdt':
                            default:
                                $invoice = [
                                    'your_payment' => $request->pair_amount,
                                    'wage_percent' => $wage,
                                    'currency_type_wage_percent' => 'تتر',
                                    'your_receipt' => $total_tether,
                                    'currency_type_your_receipt' => 'تتر',
                                    'currency_type' => strtoupper($request->pair_type)
                                ];
                                break;
                            case 'toman':
                                $invoice = [
                                    'your_payment' => $request->pair_amount,
                                    'wage_percent' => $wage,
                                    'currency_type_wage_percent' => 'تتر',
                                    'your_receipt' => $toman_amount,
                                    'currency_type_your_receipt' => 'تتر',
                                    'currency_type' => strtoupper($request->pair_type)
                                ];
                                break;
                        }

                        if ($request->request_type == 'invoice') {

                            return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);

                        } elseif ($request->request_type == 'sell') {
                            $user_wallet = $this->userWalletsRepository
                                ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));
                            if ($user_wallet->amount < $request->pair_amount) {
                                $required_exchange_amount = $request->pair_amount - $user_wallet->amount;
                                $data = [
                                    'required_exchange_amount' => $required_exchange_amount
                                ];

                                return Response::failed(__('sell_exchange_failed_more_currency_needed'), $data, 422, -2);

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
                                        'client_id' => Auth::guard('api')->user()->mobile
                                    ];

                                    SellExchangeTrait::SellOrderCoinex($coinex_order_info, 'market');
                                }


                                return Response::success(__('sell_exchange_success_sold'), null, 200);


                            }
                        }
                    }
                }
                break;

        }
//      end market invoice or sell exchanges

    }

    public function limit(Request $request)
    {

        $santizier = new Sanitizer($request->all(), [
            'pair_type' => 'strip_tags',
            'type' => 'strip_tags',
            'pair_price' => 'strip_tags',
            'pair_amount' => 'strip_tags',
            'request_type' => 'strip_tags',
            'receipt_type' => 'strip_tags'
        ]);

        $request_sanitized = $santizier->sanitize();

        $validator = Validator::make($request_sanitized, [
            'pair_type' => ['string'],
            'type' => ['string'],
            'pair_price' => ['numeric'],
            'pair_amount' => ['numeric'],
            'request_type' => ['string'],
            'receipt_type' => ['string']
        ]);

        if ($validator->fails()) {
            return Response::failed($validator->errors()->toArray(), null, 422, -1);
        }

        $tether_price = UsdtPrice::get()->quantity / 10;
        $static_percent = \config('bitazar.coinex.wage_percent');

//      Limit invoice or sell tether
        if ($request->pair_type == 'USDT' || $request->pair_type == 'usdt') {
            $total_price = $request->pair_amount * $request->pair_price;
            $data = [
                'your_payment' => $request->pair_amount,
                'wage_percent' => 0,
                'currency_type_wage_percent' => 'تومان',
                'your_receipt' => $total_price,
                'currency_type_your_receipt' => 'تومان',
                'currency_type' => 'تتر'
            ];
            if($request->request_type == 'invoice') {

                return Response::success(__('trade_exchange_success_tether_amount_calculated'), $data, 200);

            } elseif($request->request_type == 'sell'){

                $usdt_freeze_amount = FreezeHelper::getFreezeAmount('USDT');

                $user_wallet = $this->userWalletsRepository
                    ->getUserWallet(Auth::guard('api')->id(), 'USDT');

                if(($user_wallet->amount - $usdt_freeze_amount) < $request->pair_amount){
                    $required_tether_amount  =  $request->pair_amount - ($user_wallet->amount - $usdt_freeze_amount);
                    $data = [
                        'required_tether_amount' => $required_tether_amount
                    ];

                    return Response::failed(__('trade_exchange_failed_tether_are_needed'), $data, 422, -1);


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
                        'toman_wage' => $tether_price * $wage,
                        'filled' => 0,
                        'status' => OrdersList::PENDING_STATUS,
                    ];

                    $order_info = OrderRepository::storeBuyOrders($order_info);

                    $order_setting = OrderSetting::checkOrderMode();
                    if($order_setting->mode == OrderSetting::AUTOMATIC_MODE)
                    {
                        $coinex_order_info = [
                            'access_id' => config('bitazar.coinex.access_id'),
                            'market' => strtoupper($request->pair_type) . 'USDT',
                            'type' => 'sell',
                            'amount' => $request->pair_amount,
                            'tonce' => now()->toDateTimeString(),
                            'account_id' => 0,
                            'client_id' => Auth::guard('api')->user()->mobile
                        ];

                        SellExchangeTrait::SellOrderCoinex($coinex_order_info, 'limit');
                    }

                    return Response::success(__('trade_exchange_success_tether_charged'), null, 200);

                }

            }
        }
//      end limit invoice or sell tether

//      Limit invoice or sell exchanges
        $exchange_setting = ExchangeSetting::getExchangeSettings();
        switch ($exchange_setting->exchange) {
            case 'کوینکس':
            default:
                // Get coinex market info for find sepcific currency and min amount and taker fee rate
                $coinex_market_info = CoinexMarketInfoExtracted::findMarketInfo(strtoupper($request->pair_type) . 'USDT');
                $coinex_market_info = json_decode($coinex_market_info->data);
                $maker_fee_rate = \config('bitazar.coinex.network_wage_percent');

                $invoice = [];
                $wage = SellExchangeTrait::calculateWage($request->pair_amount, $request->pair_price, $static_percent);
                $total_tether = SellExchangeTrait::calculateTotalTether($request->pair_amount, $request->pair_price, $maker_fee_rate, $static_percent);
                $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tether_price);
                $maker_fee_rate = SellExchangeTrait::calculateNetworkWage($request->pair_amount, $maker_fee_rate, $request->pair_price);

                switch ($request->receipt_type)
                {
                    case 'usdt':
                    default:
                        $invoice =  [
                            'your_payment' => $request->pair_amount,
                            'wage_percent' => $wage,
                            'network_wage' => $maker_fee_rate,
                            'currency_type_wage_percent' => 'تتر',
                            'your_receipt' => $total_tether,
                            'currency_type_your_receipt' => 'تتر',
                            'currency_type' => strtoupper($request->pair_type)
                        ];
                        break;
                    case 'toman':
                        $invoice =  [
                            'your_payment' => $request->pair_amount,
                            'wage_percent' => $wage * $tether_price,
                            'network_wage' => $maker_fee_rate * $tether_price,
                            'currency_type_wage_percent' => 'تتر',
                            'your_receipt' => $toman_amount,
                            'currency_type_your_receipt' => 'تومان',
                            'currency_type' => strtoupper($request->pair_type)
                        ];
                        break;
                }

                if($request->request_type == 'invoice') {

                    return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);

                } elseif($request->request_type == 'sell'){

                    $pair_freeze_amount = FreezeHelper::getFreezeAmount(strtoupper($request->pair_type));

                    $user_wallet = $this->userWalletsRepository
                        ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));

                    if(($user_wallet->amount - $pair_freeze_amount) < $request->pair_amount)
                    {
                        $required_exchange_amount  =  $request->pair_amount - ($user_wallet->amount - $pair_freeze_amount);
                        $data = [
                            'required_'.strtolower($request->pair_type).'_amount' => $required_exchange_amount
                        ];

                        return Response::failed(__('sell_exchange_failed_more_currency_needed'), $data, 422, -2);

                    }
                    else{


                        $order_setting = OrderSetting::checkOrderMode();
                        if($order_setting->mode == OrderSetting::AUTOMATIC_MODE)
                        {
                            $coinex_order_info = [
                                'access_id' => config('bitazar.coinex.access_id'),
                                'market' => strtoupper($request->pair_type) . 'USDT',
                                'type' => 'sell',
                                'amount' => $request->pair_amount,
                                'price' => $request->pair_price,
                                'tonce' => now()->toDateTimeString(),
                                'account_id' => 0,
                                'client_id' => Auth::guard('api')->user()->mobile
                            ];


                            $coinex_order_result = SellExchangeTrait::SellOrderCoinex($coinex_order_info, 'limit');
                        }


                        if (!@$coinex_order_result->code) {

                            $order_info = [
                                'user_id' => Auth::guard('api')->id(),
                                'time' => now(),
                                'market' => strtoupper($request->pair_type) . 'USDT',
                                'type' => OrdersList::LIMIT_TYPE,
                                'role' => OrdersList::SELL_ROLE,
                                'order_price' => $toman_amount,
                                'avg_price' => 0,
                                'amount' => $request->pair_amount,
                                'total_price' => $total_tether,
                                'current_wage' => $wage,
                                'toman_wage' => $tether_price * $wage,
                                'filled' => 0,
                                'status' => OrdersList::PENDING_STATUS,
                            ];
                            $order_info = OrderRepository::storeSellOrders($order_info);

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
                            SmsHelper::sendMessage(Auth::guard('api')->user()->mobile, $this->templates_id['sell_limit_order'], [$request->pair_amount, $request->pair_type]);


                            return Response::success(__('trade_exchange_success_order'), null, 200);


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
                        $maker_fee_rate = $market_info->makerFeeRate;
                        $invoice = [];
                        $wage = SellExchangeTrait::calculateWage($request->pair_amount, $request->pair_price, $static_percent);
                        $total_tether = SellExchangeTrait::calculateTotalTether($request->pair_amount, $request->pair_price, $maker_fee_rate, $static_percent);
                        $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tether_price);
                        switch ($request->receipt_type)
                        {
                            case 'usdt':
                            default:
                                $invoice =  [
                                    'your_payment' => $request->pair_amount,
                                    'wage_percent' => $wage,
                                    'currency_type_wage_percent' => 'تتر',
                                    'your_receipt' => $total_tether,
                                    'currency_type_your_receipt' => 'تتر',
                                    'currency_type' => strtoupper($request->pair_type)
                                ];
                                break;
                            case 'toman':
                                $invoice =  [
                                    'your_payment' => $request->pair_amount,
                                    'wage_percent' => $wage,
                                    'currency_type_wage_percent' => 'تتر',
                                    'your_receipt' => $toman_amount,
                                    'currency_type_your_receipt' => 'تومان',
                                    'currency_type' => strtoupper($request->pair_type)
                                ];
                                break;
                        }

                        if($request->request_type == 'invoice') {

                            return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);

                        } elseif($request->request_type == 'sell'){
                            $user_wallet = $this->userWalletsRepository
                                ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));
                            if($user_wallet->amount < $request->pair_amount)
                            {
                                $required_exchange_amount  =  $request->pair_amount - $user_wallet->amount;
                                $data = [
                                    'required_'.strtolower($request->pair_type).'_amount' => $required_exchange_amount
                                ];

                                return Response::failed(__('sell_exchange_failed_more_currency_needed'), $data, 422, -2);

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
                                    'toman_wage' => $tether_price * $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::PENDING_STATUS,
                                ];

                                OrderRepository::storeBuyOrders($order_info);

                                $order_setting = OrderSetting::checkOrderMode();
                                if($order_setting->mode == OrderSetting::AUTOMATIC_MODE)
                                {
                                    $coinex_order_info = [
                                        'access_id' => config('bitazar.coinex.access_id'),
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => 'sell',
                                        'amount' => $request->pair_amount,
                                        'tonce' => now()->toDateTimeString(),
                                        'account_id' => 0,
                                        'client_id' => Auth::guard('api')->user()->mobile
                                    ];

                                    SellExchangeTrait::SellOrderCoinex($coinex_order_info, 'limit');
                                }


                                return Response::success(__('sell_exchange_success_sold'), null, 200);

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
                        $toman_amount = SellExchangeTrait::calculateTomanAmount($total_tether, $tether_price);
                        switch ($request->receipt_type)
                        {
                            case 'usdt':
                            default:
                                $invoice =  [
                                    'your_payment' => $request->pair_amount,
                                    'wage_percent' => $wage,
                                    'currency_type_wage_percent' => 'تتر',
                                    'your_receipt' => $total_tether,
                                    'currency_type_your_receipt' => 'تتر',
                                    'currency_type' => strtoupper($request->pair_type)
                                ];
                                break;
                            case 'toman':
                                $invoice =  [
                                    'your_payment' => $request->pair_amount,
                                    'wage_percent' => $wage,
                                    'currency_type_wage_percent' => 'تتر',
                                    'your_receipt' => $toman_amount,
                                    'currency_type_your_receipt' => 'تومان',
                                    'currency_type' => strtoupper($request->pair_type)
                                ];
                                break;
                        }

                        if($request->request_type == 'invoice') {

                            return Response::success(__('trade_exchange_success_your_exchange_calculated'), $invoice, 200);

                        } elseif($request->request_type == 'sell'){
                            $user_wallet = $this->userWalletsRepository
                                ->getUserWallet(Auth::guard('api')->id(), strtoupper($request->pair_type));
                            if($user_wallet->amount < $request->pair_amount)
                            {
                                $required_exchange_amount  =  $request->pair_amount - $user_wallet->amount;
                                $data = [
                                    'required_'.strtolower($request->pair_type).'_amount' => $required_exchange_amount
                                ];

                                return Response::failed(__('sell_exchange_failed_more_currency_needed'), $data, 422, -2);

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
                                    'toman_wage' => $tether_price * $wage,
                                    'filled' => 0,
                                    'status' => OrdersList::PENDING_STATUS,
                                ];

                                OrderRepository::storeBuyOrders($order_info);

                                $order_setting = OrderSetting::checkOrderMode();
                                if($order_setting->mode == OrderSetting::AUTOMATIC_MODE)
                                {
                                    $coinex_order_info = [
                                        'access_id' => config('bitazar.coinex.access_id'),
                                        'market' => strtoupper($request->pair_type) . 'USDT',
                                        'type' => 'sell',
                                        'amount' => $request->pair_amount,
                                        'tonce' => now()->toDateTimeString(),
                                        'account_id' => 0,
                                        'client_id' => Auth::guard('api')->user()->mobile
                                    ];

                                    SellExchangeTrait::SellOrderCoinex($coinex_order_info, 'limit');
                                }


                                return Response::success(__('sell_exchange_success_sold'), null, 200);

                            }

                        }
                    }
                }
                break;
        }

    }

}
