<?php

namespace App\Http\Controllers\ColdRooms;

use App\Http\Controllers\Controller;
use App\Http\Requests\ColdRooms\ColdRoomRequest;
use App\Http\Requests\ColdRooms\ReadingRequest;
use App\Models\Alert;
use App\Models\ColdRoom;
use App\Models\TemperatureRecord;
use App\Models\WarehouseLocation;
use App\Services\ColdRoomService;
use App\Support\CurrentWarehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ColdRoomController extends Controller
{
    public function __construct(private readonly ColdRoomService $coldRooms)
    {
    }

    public function index(Request $request): View
    {
        $rooms = ColdRoom::query()->with('location:id,name')
            ->when(! $request->boolean('inactive'), fn ($q) => $q->where('active', true))
            ->orderBy('code')->paginate($this->perPage($request))->withQueryString();

        $openAlerts = Alert::query()->open()->where('type', 'temperature')
            ->where('alertable_type', (new ColdRoom)->getMorphClass())
            ->pluck('alertable_id')->flip();

        return view('cold-rooms.index', [
            'rooms' => $rooms,
            'latest' => $this->coldRooms->latestReadings($rooms->pluck('id')->all()),
            'openAlerts' => $openAlerts,
            'summary' => $this->coldRooms->summary(),
        ]);
    }

    public function create(): View
    {
        return view('cold-rooms.form', $this->formData(new ColdRoom(['temp_min' => 0, 'temp_max' => 8, 'active' => true])));
    }

    public function store(ColdRoomRequest $request): RedirectResponse
    {
        $room = ColdRoom::query()->create($request->validated());

        return redirect()->route('cold-rooms.show', $room)->with('success', 'Cámara creada.');
    }

    public function show(Request $request, ColdRoom $coldRoom): View
    {
        $readings = TemperatureRecord::query()->where('cold_room_id', $coldRoom->id)
            ->with('user:id,first_name,last_name')
            ->when($request->boolean('out_of_range'), fn ($q) => $q->where('out_of_range', true))
            ->latest('recorded_at')->latest('id')
            ->paginate($this->perPage($request))->withQueryString();

        return view('cold-rooms.show', [
            'room' => $coldRoom->load('location'),
            'readings' => $readings,
            'chart' => $this->coldRooms->chartData($coldRoom, 48),
            'latest' => $this->coldRooms->latestReadings([$coldRoom->id])->get($coldRoom->id),
            'alert' => Alert::query()->where('fingerprint', ColdRoomService::alertFingerprint($coldRoom))->open()->first(),
        ]);
    }

    public function edit(ColdRoom $coldRoom): View
    {
        return view('cold-rooms.form', $this->formData($coldRoom));
    }

    public function update(ColdRoomRequest $request, ColdRoom $coldRoom): RedirectResponse
    {
        $coldRoom->update($request->validated());

        return redirect()->route('cold-rooms.show', $coldRoom)->with('success', 'Cámara actualizada.');
    }

    public function storeReading(ReadingRequest $request, ColdRoom $coldRoom): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $record = $this->coldRooms->recordReading(
            $coldRoom,
            (float) $data['temperature'],
            isset($data['humidity']) ? (float) $data['humidity'] : null,
            'manual',
            ! empty($data['recorded_at']) ? Carbon::parse($data['recorded_at']) : null,
        );

        $message = $record->out_of_range
            ? 'Lectura registrada FUERA DE RANGO: se generó una alerta crítica.'
            : 'Lectura registrada.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'out_of_range' => $record->out_of_range, 'id' => $record->id]);
        }

        return redirect()->route('cold-rooms.show', $coldRoom)->with($record->out_of_range ? 'warning' : 'success', $message);
    }

    private function formData(ColdRoom $room): array
    {
        return [
            'room' => $room,
            'locations' => WarehouseLocation::query()->where('warehouse_id', CurrentWarehouse::id())->orderBy('code')->get()
                ->mapWithKeys(fn ($l) => [$l->id => $l->code.' — '.$l->name]),
        ];
    }
}
