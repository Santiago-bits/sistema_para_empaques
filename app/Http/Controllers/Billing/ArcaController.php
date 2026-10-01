<?php

namespace App\Http\Controllers\Billing;

use App\Enums\ArcaMode;
use App\Http\Controllers\Controller;
use App\Models\ArcaRecord;
use App\Models\Invoice;
use App\Services\Arca\ArcaGatewayFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Panel de ARCA: modo actual, estado de credenciales, últimos envíos y prueba de conexión. */
class ArcaController extends Controller
{
    public function index(): View
    {
        $config = config('galpon.arca');

        return view('arca.index', [
            'mode' => ArcaMode::tryFrom((string) setting('arca.mode', 'simulation')) ?? ArcaMode::Simulation,
            'pointOfSale' => setting('arca.point_of_sale', 1),
            'cuit' => setting('arca.cuit') ?: $config['cuit'],
            'emitter' => setting('arca.emitter_condition', 'RI'),
            // Sólo se informa si existen, nunca la ruta ni el contenido.
            'certificateOk' => ! empty($config['certificate_path']) && is_readable($config['certificate_path']),
            'keyOk' => ! empty($config['private_key_path']) && is_readable($config['private_key_path']),
            'records' => ArcaRecord::query()->with(['invoice:id,voucher_type,point_of_sale,number,status', 'user:id,first_name,last_name'])
                ->latest('created_at')->latest('id')->limit(30)->get(),
            'pending' => Invoice::query()->whereIn('status', ['pending', 'rejected'])->count(),
            'environment' => app()->environment(),
        ]);
    }

    public function test(ArcaGatewayFactory $factory): RedirectResponse
    {
        $result = $factory->make()->testConnection();

        return back()->with($result->approved ? 'success' : 'error',
            $result->approved ? 'Conexión con ARCA correcta.' : 'No se pudo conectar con ARCA: '.$result->error);
    }
}
