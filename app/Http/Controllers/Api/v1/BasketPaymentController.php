<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Api\v1\BasketIncrementalLog;
use App\Models\Api\v1\KeepingCostLog;
use App\Models\User;
use App\Helpers\Helpers;
use Illuminate\Support\Arr;
use App\Models\Api\v1\Order;
use Illuminate\Http\Request;
use App\Models\Api\v1\Basket;
use App\Models\Api\v1\Wallet;
use App\Helpers\PaymentHelper;
use App\Models\Api\v1\Product;
use Elegant\Sanitizer\Sanitizer;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class BasketPaymentController extends Controller
{

    protected $NEXT_PAY_API_KEY;

    public function __construct()
    {
        $this->NEXT_PAY_API_KEY = config('kandoapi.NEXT_PAY_API_KEY');
    }

    public function generate(Request $request)
    {
        $santizier = new Sanitizer($request->all(), [
            'products_id' => 'strip_tags',
            'quantities' => 'strip_tags',
        ]);

        $request_sanitized = $santizier->sanitize();

        $validator = Validator::make($request_sanitized, [
            'products_id' => ['required'],
            'quantities' => ['required'],
        ]);

        if ($validator->fails()) {
            return Response::failed($validator->errors()->toArray(), null, -1);
        }

        $total_price = 0;
        if (str_contains($request->products_id, ',')) {

            $products_id = explode(',', $request->products_id);
            $quantities = explode(',', $request->quantities);
            $products = Product::multiGet($products_id);

            $i = 0;
            foreach ($products as $product) {
                $total_price += $quantities[$i] * $product->price;
                $incremental_types[] = $product->category->en_name;
                $prices[] = $product->price;
                $i++;
            }

            $new_record = [
                'user_id' => Auth::guard('api')->id(),
                'prices' => implode(',', $prices),
                'incremental_amounts' => $request->quantities,
                'incremental_types' => implode(',', $incremental_types),
                'total_price' => $total_price,
            ];
            $basket_id = BasketIncrementalLog::storeWithIncrementals($new_record);

            $payment_helper = new PaymentHelper();
            $payment_link = $payment_helper->nextPay($total_price, @Auth::guard('api')->mobile, ['user_id' => Auth::guard('api')->id(), 'product_id' => $request->products_id, 'basket_id' => $basket_id, 'request_url' => base64_decode($request->client_url)],null, 'https://devapi.ehyakhak.com/basket/verify/');

        } else {
            $product = Product::find($request->products_id);
            $total_price = $request->quantities * $product->price;
            $incremental_types = $product->category->en_name;

            $new_record = [
                'user_id' => Auth::guard('api')->id(),
                'prices' => $product->price,
                'incremental_amounts' => $request->quantities,
                'incremental_types' => $incremental_types,
                'total_price' => $total_price,
            ];
            $basket_id = BasketIncrementalLog::storeWithIncrementals($new_record);

            $payment_helper = new PaymentHelper();
            $payment_link = $payment_helper->nextPay($total_price, @Auth::guard('api')->mobile, ['user_id' => Auth::guard('api')->id(), 'product_id' => $request->products_id, 'basket_id' => $basket_id, 'request_url' => base64_decode($request->client_url)], null,'https://devapi.ehyakhak.com/basket/verify/');
        }

        $data = [
            'payment_link' => $payment_link
        ];
        return Response::success('لینک درگاه پرداخت', $data, 1);


    }

    public function verify(Request $request)
    {

        $data = [
            'api_key' => $this->NEXT_PAY_API_KEY,
            'trans_id' => $request->trans_id,
            'amount' => $request->amount
        ];

        // Send request to nextpay
        $result = Http::post('https://nextpay.org/nx/gateway/verify', $data)->body();
        $result = json_decode($result);

        $custom_data = json_decode($result->custom);
        $user_id = $custom_data->user_id;
        $product_id = $custom_data->product_id;
        $basket_id = $custom_data->basket_id;
        $request_url = $custom_data->request_url;

        // Check when havent any errors
        if ($result->code == '0') {

            $user = User::find($user_id);
            $basket = BasketIncrementalLog::getIncrementals($basket_id);
            $incremental_amounts = $basket->incremental_amounts;
            $incremental_types = $basket->incremental_types;
            if (str_contains($incremental_amounts, ',')) {
                $incremental_amounts = explode(',', $incremental_amounts);
                $incremental_types = explode(',', $incremental_types);
                for ($i = 0; $i < count($incremental_amounts); $i++) {
                    Wallet::increaseAmountUser($user->id, $incremental_types[$i], $incremental_amounts[$i]);
                }
            } else {
                Wallet::increaseAmountUser($user->id, $incremental_types, $incremental_amounts);
            }

            $reference_id = $result->Shaparak_Ref_Id;

            foreach ($user->baskets as $user_basket) {
                $user_basket->status = '1';
                $user_basket->save();
            }



            $order = [
                'user_id' => $user->id,
                'card_holder' => @$result->card_holder,
                'product_id' => $product_id,
                'price' => $basket->prices,
                'count' => $basket->incremental_amounts,
                'total_price' => $basket->total_price,
                'tracking_id' => $reference_id,
                'status' => '1'
            ];
            $order_id = Order::store($order);


            foreach ($user->baskets as $user_basket) {
                $log = [
                    'user_id' => $user->id,
                    'basket_id' => $user_basket->id,
                    'keeping_cost' => @$user_basket->product->cost_keep_honey,
                    'status' => '1',
                    'product_id' => $user_basket->product_id,
                    'type' => $user_basket->product->category->en_name,
                    'count' => $user_basket->count,
                ];
                // Store log of user payment about keeping cost
                KeepingCostLog::store($log);
            }

            $base_url = $request_url . 'cart';
            $variables = [
                'order_id' => $order_id,
                'partner' => 'false'
            ];
            $query_string = Arr::query($variables);
            $data = $base_url . '?' . $query_string;

            return redirect($data);

        } else {

            $reference_id = @$result->Shaparak_Ref_Id ?: Helpers::UniqueCode(5);

            $user = User::find($user_id);

            foreach ($user->baskets as $user_basket) {
                $user_basket->status = '-1';
                $user_basket->save();
            }

            $order = [
                'user_id' => $user->id,
                'card_holder' => @$result->card_holder,
                'product_id' => $product_id,
                'price' => $basket->prices,
                'count' => $basket->incremental_amounts,
                'total_price' => $basket->total_price,
                'tracking_id' => $reference_id,
                'status' => '-1'
            ];
            $order_id = Order::store($order);

            $base_url = $request_url . 'cart';
            $variables = [
                'order_id' => $order_id,
                'partner' => 'false'
            ];
            $query_string = Arr::query($variables);
            $data = $base_url . '?' . $query_string;

            return redirect($data);

        }

    }

}
