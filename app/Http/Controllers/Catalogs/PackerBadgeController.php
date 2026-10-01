<?php

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Models\Packer;
use App\Services\PackerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Credenciales imprimibles con código de barras Code128 para escanear al embalador. */
class PackerBadgeController extends Controller
{
    public function __construct(private readonly PackerService $packers)
    {
    }

    public function show(Packer $packer): View
    {
        return $this->render(collect([$packer]));
    }

    public function many(Request $request): View
    {
        $ids = collect((array) $request->query('ids', []))->filter(fn ($id) => ctype_digit((string) $id))->take(200);
        $packers = $ids->isEmpty()
            ? Packer::query()->where('active', true)->orderBy('code')->limit(200)->get()
            : Packer::query()->whereIn('id', $ids)->orderBy('code')->get();

        return $this->render($packers);
    }

    private function render($packers): View
    {
        return view('packers.badges', [
            'packers' => $packers->map(fn (Packer $p) => [
                'packer' => $p,
                'barcode' => $this->packers->barcodeSvg($p->code),
            ]),
        ]);
    }
}
