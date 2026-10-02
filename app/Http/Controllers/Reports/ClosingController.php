<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\DailyClosing;
use App\Services\DailyClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ClosingController extends Controller
{
    public function __construct(private readonly DailyClosingService $closings)
    {
    }

    public function index(Request $request): View
    {
        return view('closings.index', [
            'closings' => DailyClosing::query()->with(['closer:id,first_name,last_name', 'reopener:id,first_name,last_name'])
                ->latest('date')->paginate($this->perPage($request))->withQueryString(),
            'todayClosed' => $this->closings->isClosed(today()),
        ]);
    }

    /** Vista previa del cierre de un día (por defecto hoy). */
    public function create(Request $request): View
    {
        $request->validate(['date' => ['nullable', 'date', 'before_or_equal:today']]);
        $date = $request->filled('date') ? Carbon::parse($request->query('date'))->startOfDay() : today();

        return view('closings.create', [
            'date' => $date,
            'snapshot' => $this->closings->snapshot($date),
            'closed' => $this->closings->isClosed($date),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $closing = $this->closings->close(Carbon::parse($data['date'])->startOfDay(), $request->user(), $data['notes'] ?? null);

        return redirect()->route('closings.show', $closing)->with('success', 'Día cerrado. El resumen quedó guardado.');
    }

    public function show(DailyClosing $closing): View
    {
        return view('closings.show', ['closing' => $closing->load('closer', 'reopener')]);
    }

    public function reopen(Request $request, DailyClosing $closing): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $this->closings->reopen($closing, $data['reason'], $request->user());

        return redirect()->route('closings.show', $closing)->with('success', 'Cierre reabierto. Podés volver a cerrarlo para actualizar el resumen.');
    }
}
