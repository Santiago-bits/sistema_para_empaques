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
    public function index(Request $request, ExchangeRateService $service): View
    {
        // Por si el servidor no tiene el programador de tareas: al abrir la pantalla se actualiza si ya tocaba.
        $service->autoUpdate();

        return view('treasury.exchange.index', [
            'current' => ExchangeRate::current(),
            'rates' => ExchangeRate::query()->where('currency', 'USD')->with('user:id,first_name,last_name')
                ->latest('date')->paginate($this->perPage($request))->withQueryString(),
            'autoEnabled' => ExchangeRateService::autoEnabled(),
            'autoType' => ExchangeRateService::autoType(),
            'lastFetch' => ExchangeRateService::lastFetch(),
        ]);
    }

    /** Trae el valor de hoy de internet: dólar oficial (Banco Nación) o blue. */
    public function fetch(Request $request, ExchangeRateService $service): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(ExchangeRateService::ONLINE_TYPES))]]);

        $rate = $service->fetchOnline($data['type'], $request->user());

        return redirect()->route('exchange.index')->with('success', ExchangeRateService::TYPE_LABELS[$data['type']].' de hoy actualizado: US$ 1 = '.money($rate->sell).'.');
    }

    /** Activa o apaga la actualización automática cada 6 horas y elige oficial o blue. */
    public function auto(Request $request, ExchangeRateService $service): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'type' => ['required', Rule::in(array_keys(ExchangeRateService::ONLINE_TYPES))],
        ]);

        $service->configureAuto((bool) $data['enabled'], $data['type']);
        if ($data['enabled']) {
            $service->autoUpdate();
        }

        return redirect()->route('exchange.index')->with('success', $data['enabled']
            ? 'Actualización automática activada: '.ExchangeRateService::TYPE_LABELS[$data['type']].' cada '.ExchangeRateService::AUTO_HOURS.' horas.'
            : 'Actualización automática apagada. El valor se carga a mano o con los botones.');
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
