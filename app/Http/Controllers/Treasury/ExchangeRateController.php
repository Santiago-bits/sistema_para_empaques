<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExchangeRateController extends Controller
{
    public function index(Request $request): View
    {
        return view('treasury.exchange.index', [
            'current' => ExchangeRate::current(),
            'rates' => ExchangeRate::query()->where('currency', 'USD')->with('user:id,first_name,last_name')
                ->latest('date')->paginate($this->perPage($request))->withQueryString(),
        ]);
    }

    public function store(Request $request, ExchangeRateService $rates): RedirectResponse
    {
        $request->merge([
            // «1.240» = mil doscientos cuarenta; «1.234,50» y «1234.5» también se aceptan.
            'sell' => parse_number($request->input('sell')),
            'buy' => parse_number($request->input('buy')),
        ]);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'sell' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'buy' => ['nullable', 'numeric', 'gt:0', 'lte:sell', 'max:99999999'],
            'source' => ['nullable', Rule::in(array_keys(ExchangeRate::SOURCES))],
        ], ['buy.lte' => 'La cotización comprador no puede ser mayor que la vendedor.'],
            ['date' => 'fecha', 'sell' => 'vendedor', 'buy' => 'comprador', 'source' => 'fuente']);

        $rate = $rates->save($data, $request->user());

        return redirect()->route('exchange.index')->with('success', 'Cotización del '.$rate->date->format('d/m/Y').' guardada: US$ 1 = '.money($rate->sell).'.');
    }
}
