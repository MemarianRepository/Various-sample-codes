<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\SmsHelper;
use App\Http\Controllers\Controller;
use App\Models\Api\v1\DoneOrdersList;
use App\Models\Api\v1\OrdersList;
use App\Models\Api\v1\UpdateOrderLog;
use App\Models\User;
use App\Notifications\UserMessagesNotification;
use App\Repositories\Api\v1\UserWalletsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Response;
use Elegant\Sanitizer\Sanitizer;

class UpdateOrdersController extends Controller
{

    private const CANCEL_DEAL = '-1';
    private const NO_DEAL = '0';
    private const DONE_DEAL = '1';
    private const PART_DEAL = '2';

    private const STR_CANCEL_DEAL = 'cancel';
    private const STR_NO_DEAL = 'not_deal';
    private const STR_DONE_DEAL = 'done';
    private const STR_PART_DEAL = 'part_deal';

    private $userWalletsRepository;

    public function __construct(UserWalletsRepository $userWalletsRepository)
    {
        $this->userWalletsRepository = $userWalletsRepository;
    }

    /**
     * @group Update order section
     * Api to update order from node js data
     * @json  {
     *            "method": "order.update",
     *            "params" :    [
     *                2,
     *                [
     *                {
     *                    "asset_fee": "0",
     *                    "account": 1,
     *                    "option": 2,
     *                   "money_fee": "0",
     *                    "stock_fee": "0",
     *                    "ctime": 1558680350.41878,
     *                    "maker_fee": "0.0001",
     *                    "price": "0.01900000",
     *                    "deal_stock": "0",
     *                    "fee_discount": "0.9000",
     *                    "side": 1,
     *                    "source": "api",
     *                    "amount": "1.00000000",
     *                    "user": 553,
     *                    "mtime": 1558680350.41878,
     *                    "fee_asset": "CET",
     *                    "last_deal_amount": "52.22635926",
     *                    "last_deal_price": "0",
     *                    "last_deal_time": "0",
     *                    "last_deal_id": "0",
     *                    "last_role": 2,
     *                    "deal_money": "0",
     *                    "left": "1.00000000",
     *                    "type": 1,
     *                    "id": 91256852791,
     *                    "market": "BTCUSDT",
     *                    "taker_fee": "0.0001",
     *                    "client_id": "test_123",
     *                    "stop_id": 0
     *                }
     *            ]
     *        ]
     *     }
     **/
    public function store(Request $request)
    {

        // Get json from request
        $new_updates = $request->getContent();

        UpdateOrderLog::store(json_encode($new_updates));

        $new_updates = json_decode($new_updates, true);

        // Flag to detect successful operation
        $success = false;

        for ($i = 1; $i < count($new_updates['params']); $i++) {

            if ($new_updates['params'][$i]['type'] == '1') {
                // Prepare new done order to update
                $done_order = [
                    'identifier' => @$new_updates['params'][$i]['id'],
                    'type' => @$new_updates['params'][$i]['type'],
                    'side' => @$new_updates['params'][$i]['side'],
                    'user' => @$new_updates['params'][$i]['user'],
                    'account' => @$new_updates['params'][$i]['account'],
                    'option' => @$new_updates['params'][$i]['option'],
                    'amount' => @$new_updates['params'][$i]['amount'],
                    'create_time' => @$new_updates['params'][$i]['ctime'],
                    'finished_time' => @$new_updates['params'][$i]['mtime'],
                    'market' => @$new_updates['params'][$i]['market'],
                    'source' => @$new_updates['params'][$i]['source'],
                    'price' => @$new_updates['params'][$i]['price'],
                    'client_id' => @$new_updates['params'][$i]['client_id'],
                    'taker_fee_rate' => @$new_updates['params'][$i]['taker_fee'],
                    'maker_fee_rate' => @$new_updates['params'][$i]['maker_fee'],
                    'left' => @$new_updates['params'][$i]['left'],
                    'deal_stock' => @$new_updates['params'][$i]['deal_stock'],
                    'deal_money' => @$new_updates['params'][$i]['deal_money'],
                    'money_fee' => @$new_updates['params'][$i]['money_fee'],
                    'stock_fee' => @$new_updates['params'][$i]['stock_fee'],
                    'asset_fee' => @$new_updates['params'][$i]['asset_fee'],
                    'deal_fee' => @$new_updates['params'][$i]['stock_fee'],
                    'fee_discount' => @$new_updates['params'][$i]['fee_discount'],
                    'deal_amount' => @$new_updates['params'][$i]['last_deal_amount'],
                    'last_deal_price' => @$new_updates['params'][$i]['last_deal_price'],
                    'last_deal_time' => @$new_updates['params'][$i]['last_deal_time'],
                    'last_deal_id' => @$new_updates['params'][$i]['last_deal_id'],
                    'last_role' => @$new_updates['params'][$i]['last_role'],
                    'stop_id' => @$new_updates['params'][$i]['stop_id'],
                    'fee_asset' => @$new_updates['params'][$i]['fee_asset'],
                ];
                // Define order status
                switch ($new_updates['params'][0]) {
                    case 2:
                        // Set status to pending
                        $status = [
                            'status' => self::STR_PART_DEAL
                        ];
                        $done_order = array_merge($done_order, $status);
                        break;

                    case 3:
                        if ($new_updates['params'][$i]['last_deal_id'] == 0 || $new_updates['params'][$i]['last_deal_time'] == 0) {

                            $status = [
                                'status' => self::STR_CANCEL_DEAL
                            ];

                        } else {

                            $status = [
                                'status' => self::STR_DONE_DEAL
                            ];
                        }
                        $done_order = array_merge($done_order, $status);
                        break;

                }
                $order_id = DoneOrdersList::updateDoneOrders($done_order);


                if ($order_id) {

                    $order = OrdersList::findById($order_id);

                    // Detect order status
                    switch ($new_updates['params'][0]) {
                        case 2:

                            switch ($new_updates['params'][$i]['last_role']) {

                                case 1:
                                    $increase_amount = (($new_updates['params'][$i]['last_deal_amount'] * $new_updates['params'][$i]['last_deal_price']) + $order->current_wage);
                                    $this->userWalletsRepository
                                        ->increaseWalletAmount($order->user_id, 'USDT', $increase_amount);
                                    $this->userWalletsRepository
                                        ->decreaseWalletAmount($order->user_id, str_replace('USDT', '', $new_updates['params'][$i]['market']), $new_updates['params'][$i]['last_deal_amount']);
                                    break;
                                case 2:
                                    $decrease_amount = (($new_updates['params'][$i]['last_deal_amount'] * $new_updates['params'][$i]['last_deal_price']) + $order->current_wage);
                                    $this->userWalletsRepository
                                        ->decreaseWalletAmount($order->user_id, 'USDT', $decrease_amount);
                                    $this->userWalletsRepository
                                        ->increaseWalletAmount($order->user_id, str_replace('USDT', '', $new_updates['params'][$i]['market']), $new_updates['params'][$i]['last_deal_amount']);
                                    break;
                            }
                            // Update order list to pending
                            OrdersList::updateLimitOrders($order_id, OrdersList::PART_DEAL, $new_updates['params'][$i]['last_deal_amount']);
                            break;

                        case 3:
                            if ($new_updates['params'][$i]['last_deal_id'] == 0 || $new_updates['params'][$i]['last_deal_time'] == 0) {

                                // Send notification to user
                                $user = User::findById($order->user_id);
                                $message = 'سفارش شما به شماره [ID] لغو گردید';
                                $message = str_replace('[ID]', $order_id, $message);
                                Notification::send($user, new UserMessagesNotification($user, $message, 'سفارش'));

                                SmsHelper::sendMessage($user->mobile, $this->templates_id['limit_order_cancel'], [$new_updates['params'][$i]['market'], $order_id]);

                                // Update order list to cancel
                                OrdersList::updateLimitOrders($order_id, OrdersList::CANCEL_STATUS, $new_updates['params'][$i]['last_deal_amount']);

                            } else {

                                switch ($new_updates['params'][$i]['last_role']) {

                                    case 1:
                                        $title = 'فروش ارز';
                                        $increase_amount = (($new_updates['params'][$i]['last_deal_amount'] * $new_updates['params'][$i]['last_deal_price']) + $order->current_wage);
                                        $this->userWalletsRepository
                                            ->increaseWalletAmount($order->user_id, 'USDT', $increase_amount);
                                        $this->userWalletsRepository
                                            ->decreaseWalletAmount($order->user_id, str_replace('USDT', '', $new_updates['params'][$i]['market']), $new_updates['params'][$i]['last_deal_amount']);
                                        break;
                                    case 2:
                                        $title = 'خرید ارز';
                                        $decrease_amount = (($new_updates['params'][$i]['last_deal_amount'] * $new_updates['params'][$i]['last_deal_price']) + $order->current_wage);
                                        $this->userWalletsRepository
                                            ->decreaseWalletAmount($order->user_id, 'USDT', $decrease_amount);
                                        $this->userWalletsRepository
                                            ->increaseWalletAmount($order->user_id, str_replace('USDT', '', $new_updates['params'][$i]['market']), $new_updates['params'][$i]['last_deal_amount']);
                                        break;
                                }

                                // Send notification to user
                                $user = User::findById($order->user_id);
                                $message = 'سفارش شما به شماره [ID] تکمیل گردید';
                                $message = str_replace('[ID]', $order_id, $message);
                                Notification::send($user, new UserMessagesNotification($user, $message, $title));

                                SmsHelper::sendMessage($user->mobile, $this->templates_id['limit_order_complete'], [$new_updates['params'][$i]['market'], $order_id]);

                                // Update order list to done
                                OrdersList::updateLimitOrders($order_id, OrdersList::DONE_STATUS, $new_updates['params'][$i]['last_deal_amount']);

                            }
                            break;
                    }

                    // Set flag to true
                    $success = true;

                }

            }

        }

        if ($success)
            return Response::success(null, null, 200);
        else
            return Response::failed(null, null, 403, -3);

    }

}
