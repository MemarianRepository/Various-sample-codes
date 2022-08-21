<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Repositories\Api\v1\ExchangeListRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ExchangeListController extends Controller
{

    private $exchangeListRepository;

    public function __construct(ExchangeListRepository $exchangeListRepository)
    {
        $this->exchangeListRepository = $exchangeListRepository;
    }

    public function index(Request $request)
    {
        $currencies = $this->exchangeListRepository::mainCurrencies();
        $tether_price = 30000;
        $response = [];
        $response [] = [
            'id' => 17,
            'name' => 'tether',
            'persian_name' => null,
            'symbol' => 'USDT',
            'image' => null,
            'current_price' => $tether_price,
            'status' => 1
        ];

        foreach ($currencies as $exchange) {

            $response [] = [
                'id' => $exchange->id,
                'name' => $exchange->name,
                'persian_name' => null,
                'symbol' => $exchange->symbol,
                'smart_contract_name' => json_decode($exchange->smart_contract_name),
                'image' => $exchange->image,
                'current_price' => $exchange->current_price,
                'status' => $exchange->status
            ];
        }

        $currencies = $this->exchangeListRepository::getLimit(70);
        foreach ($currencies as $exchange) {
            $response [] = [
                'id' => $exchange->id,
                'name' => $exchange->name,
                'persian_name' => null,
                'symbol' => $exchange->symbol,
                'smart_contract_name' => json_decode($exchange->smart_contract_name),
                'image' => $exchange->image,
                'current_price' => $exchange->current_price,
                'status' => $exchange->status
            ];
        }

        switch ($request->lang)
        {
            case 'fa':
            default:
                return Response::success('لیست ارزهای صرافی کوینکس', $response, 200);
            case 'en':
                return Response::success('List of coinex exchange currencies', $response, 200);
        }

    }
}
