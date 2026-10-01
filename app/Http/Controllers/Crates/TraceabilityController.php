<?php

namespace App\Http\Controllers\Crates;

use App\Http\Controllers\Controller;
use App\Models\Crate;
use App\Models\Pallet;
use App\Services\TraceabilityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TraceabilityController extends Controller
{
    public function index(Request $request, TraceabilityService $traceability): View
    {
        $request->validate(['code' => ['nullable', 'string', 'max:60']]);
        $code = trim((string) $request->query('code', ''));

        $found = $code !== '' ? $traceability->find($code) : null;

        return view('traceability.index', [
            'code' => $code,
            'crateTrace' => $found instanceof Crate ? $traceability->forCrate($found) : null,
            'palletTrace' => $found instanceof Pallet ? $traceability->forPallet($found) : null,
            'notFound' => $code !== '' && $found === null,
        ]);
    }
}
