<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\Helpers;
use App\Http\Resources\Api\v1\WalletHistoryResource;
use App\Models\Api\v1\BabyBeehivePrice;
use App\Models\Api\v1\KeepingCostLog;
use Carbon\Carbon;
use App\Models\Api\v1\Order;
use Illuminate\Http\Request;
use App\Models\Api\v1\Basket;
use App\Models\Api\v1\Wallet;
use Elegant\Sanitizer\Sanitizer;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Api\v1\HoneyEquivalent;
use App\Models\Api\v1\WalletChargeLog;
use function PHPUnit\Framework\matches;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    public function getWalletInfos()
    {
        // Config
        $honey_equivalent = HoneyEquivalent::get();

        // Beehive therapy index values
        $therapeutic_honeys = Basket::getTherapeuticHoneys(Auth::guard('api')->id());
        $monthly_therapeutic_honeys = Basket::getTherapeuticHoneyLastDays(30);
        $monthly_therapeutic_honey_receipt = 0;
        $royal_jelly_amount = 0;
        $wax_amount = 0;
        $pollen_amount = 0;

        foreach ($monthly_therapeutic_honeys as $monthly_therapeutic_honey)
            $monthly_therapeutic_honey_receipt = $monthly_therapeutic_honey_receipt + ($monthly_therapeutic_honey->count * $monthly_therapeutic_honey->product->amount_honey);

        foreach ($therapeutic_honeys as $therapeutic_honey) {
            $royal_jelly_amount += round($therapeutic_honey->count * $therapeutic_honey->product->royal_jelly, 1);
            $wax_amount += $therapeutic_honey->count * $therapeutic_honey->product->wax;
            $pollen_amount += $therapeutic_honey->count * $therapeutic_honey->product->pollen;
        }

        // Beehive index values
        $normal_baby_beehive = Wallet::getBabyBeehive(Auth::guard('api')->id(), BabyBeehivePrice::NORMAL_BABY);

        $wallet = Wallet::getWallet(Auth::guard('api')->id());

        // Total keeping cost
        $cost_logs = KeepingCostLog::whereHas('product', function ($q) {
            $q->where('category_id', 1);
        })->orWhereHas('product', function ($q) {
            $q->where('category_id', 5);
        })->get();

        $cost_logs = $cost_logs->where('user_id', Auth::guard('api')->id());

        $total_cost_keep_beehive = 0;
        if ($cost_logs)
            foreach ($cost_logs as $cost_log) {
                $total_cost_keep_beehive += $cost_log->count * $cost_log->keeping_cost;
            }

        // Keeping cost of normal beehive
        $normal_beehive_costs = KeepingCostLog::whereHas('product', function ($q) {
            $q->where('category_id', 1);
        })->get();

        $normal_beehive_costs = $normal_beehive_costs->where('user_id', Auth::guard('api')->id());
        $cost_keep_beehive = 0;
        if ($normal_beehive_costs)
            foreach ($normal_beehive_costs as $normal_beehive_cost) {
                $cost_keep_beehive += $normal_beehive_cost->count * $normal_beehive_cost->keeping_cost;
            }

        // Keeping cost of special beehive
        $special_beehive_costs = KeepingCostLog::whereHas('product', function ($q) {
            $q->where('category_id', 5);
        })->get();

        // Last receipt of normal beehive
        $last_honey_receipt = WalletChargeLog::getLastHoney(Auth::guard('api')->id(), WalletChargeLog::HONEY);

        // Last receipt of special beehive
        $last_therapeutic_honey = WalletChargeLog::getLastHoney(Auth::guard('api')->id(), WalletChargeLog::THERAPEUTICHONEY);

        $special_beehive_costs = $special_beehive_costs->where('user_id', Auth::guard('api')->id());
        $cost_keep_special_beehive = 0;
        if ($special_beehive_costs)
            foreach ($special_beehive_costs as $special_beehive_cost) {
                $cost_keep_special_beehive += $special_beehive_cost->count * $special_beehive_cost->keeping_cost;
            }

        // Special beehive
        $special_baby_beehive = Wallet::getBabyBeehive(Auth::guard('api')->id(), BabyBeehivePrice::SPECIAL_BABY);

        $special_beehive_wallet = Wallet::getSpecialBeehiveWallet(Auth::guard('api')->id());

        $special_beehives = Basket::where('user_id', Auth::guard('api')->id())->where('status', '1')->whereHas('product', function ($q) {
            $q->where('category_id', 5);
        })->get();

        $total_cost_keep_special_beehive = 0;
        $total_cost_special_beehive = 0;

        foreach ($special_beehives as $special_beehive) {
            $total_cost_keep_special_beehive += @$special_beehive->count * @$special_beehive->product->cost_keep_honey;
            $total_cost_special_beehive += @$special_beehive->count * @$special_beehive->product->price;
        }

        $extra_products = Wallet::getWallets(Auth::guard('api')->id());
        if ($extra_products) {

            $total_honey = $extra_products->firstWhere('name', 'Honey');
            $total_honey = $total_honey ? $total_honey->amount : 0;

            $total_special_honey = $extra_products->firstWhere('name', 'TherapeuticHoney');
            $total_special_honey = $total_special_honey ? $total_special_honey->amount : 0;

            $total_royal_jelly = $extra_products->firstWhere('name', 'RoyalJelly');
            $total_royal_jelly = $total_royal_jelly ? $total_royal_jelly->amount : 0;

            $total_pollen = $extra_products->firstWhere('name', 'Pollen');
            $total_pollen = $total_pollen ? $total_pollen->amount : 0;

            $total_wax = $extra_products->firstWhere('name', 'Wax');
            $total_wax = $total_wax ? $total_wax->amount : 0;
        }


        // prepare two list (beehives and honeys)
        $list = [
            'beehive' => [
                'number_baby_beehive' => @$normal_baby_beehive->amount ?: 0,
                'number_beehive' => @$wallet->amount ?: 0,
                'total_amount_honey' => $total_honey,
                'cost_keep_beehive' => $cost_keep_beehive ?: 0,
                'last_receipt' => @$last_honey_receipt ? $last_honey_receipt->amount : 0,
                'last_receipt_date' => @$last_honey_receipt ? $last_honey_receipt->created_at : 'در حال حاضر دریافتی وجود ندارد',
            ],
            'special_beehive' => [
                'number_baby_special_beehive' => @$special_baby_beehive->amount ?: 0,
                'number_special_beehive' => @$special_beehive_wallet ? $special_beehive_wallet->amount : 0,
                'total_special_honey' => $total_special_honey,
                'total_royal_jelly' => $total_royal_jelly,
                'total_pollen' => $total_pollen,
                'total_wax' => $total_wax,
                'cost_special_beehive' => $cost_keep_special_beehive ?: 0,
                'last_receipt' => @$last_therapeutic_honey ? $last_therapeutic_honey->amount : 0,
                'last_receipt_date' =>  @$last_therapeutic_honey ? $last_therapeutic_honey->created_at : 'در حال حاضر دریافتی وجود ندارد',
            ],
            'config' => [
                'honey_price' => $honey_equivalent ? $honey_equivalent->honey_price : 0
            ],
            'total_cost_keep_beehive' => $total_cost_keep_beehive,
        ];


        return Response::success(trans('wallet.found'), $list);

    }
    
}
