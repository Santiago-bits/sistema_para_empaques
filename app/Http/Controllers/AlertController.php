<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\AlertChecker;
use App\Services\AlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', 'in:open,resolved,all'],
            'severity' => ['nullable', 'in:'.implode(',', array_keys(AlertService::SEVERITIES))],
            'type' => ['nullable', 'string', 'max:40'],
        ]);
        $status = $request->query('status', 'open');

        return view('alerts.index', [
            'alerts' => Alert::query()->with('resolver:id,first_name,last_name')
                ->when($status === 'open', fn ($q) => $q->open())
                ->when($status === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'))
                ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->query('severity')))
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
                ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
                ->latest('updated_at')
                ->paginate($this->perPage($request))->withQueryString(),
            'counts' => Alert::query()->open()->selectRaw('severity, COUNT(*) as total')->groupBy('severity')->pluck('total', 'severity'),
            'status' => $status,
            'types' => trans('alerts.types'),
        ]);
    }

    public function resolve(Request $request, Alert $alert, AlertService $alerts): RedirectResponse
    {
        $alerts->resolve($alert, $request->user());

        return back()->with('success', 'Alerta marcada como resuelta.');
    }

    /** Evalúa las condiciones ahora (además de la tarea programada cada 5 minutos). */
    public function check(AlertChecker $checker): RedirectResponse
    {
        $summary = collect($checker->run());
        $errors = $summary->where('status', 'error')->count();

        return back()->with($errors ? 'error' : 'success', $errors
            ? "Se evaluaron las alertas, pero {$errors} chequeo(s) fallaron. Revisá el registro de errores."
            : 'Alertas evaluadas: '.$summary->sum(fn ($r) => $r['active'] ?? 0).' activas.');
    }
}
