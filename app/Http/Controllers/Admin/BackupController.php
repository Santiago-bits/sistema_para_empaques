<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    /** Texto que hay que escribir para confirmar una restauración. */
    public const RESTORE_WORD = 'RESTAURAR';

    public function __construct(private readonly BackupService $backups)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.backups.index', [
            'backups' => Backup::query()->with('creator:id,first_name,last_name')->latest('id')
                ->paginate($this->perPage($request))->withQueryString(),
            'health' => $this->backups->health(),
            'service' => $this->backups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $backup = $this->backups->run('manual', $request->user());

        return $backup->status === 'success'
            ? back()->with('success', 'Backup generado y verificado: '.$backup->filename)
            : back()->with('error', 'El backup falló: '.$backup->error);
    }

    public function download(Request $request, Backup $backup): BinaryFileResponse
    {
        abort_unless($backup->status === 'success' && $this->backups->exists($backup), 404);
        app(\App\Services\AuditService::class)->log('download', null, null, ['filename' => $backup->filename], 'Descargó el backup '.$backup->filename);

        return response()->download($this->backups->pathFor($backup), basename($backup->filename));
    }

    public function verify(Backup $backup): RedirectResponse
    {
        $result = $this->backups->verify($backup);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Restauración con doble confirmación: escribir RESTAURAR + contraseña del usuario + motivo.
     * Antes se genera un backup automático del estado actual.
     */
    public function restore(Request $request, Backup $backup): RedirectResponse
    {
        $data = $request->validate([
            'confirmation' => ['required', 'string'],
            'password' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['confirmation' => 'confirmación', 'password' => 'contraseña', 'reason' => 'motivo']);

        if ($data['confirmation'] !== self::RESTORE_WORD) {
            throw ValidationException::withMessages(['confirmation' => 'Escribí '.self::RESTORE_WORD.' en mayúsculas para confirmar.']);
        }
        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'La contraseña no es correcta.']);
        }

        $pre = $this->backups->restore($backup, $request->user(), $data['reason']);

        return redirect()->route('backups.index')->with('success',
            'Base restaurada desde '.$backup->filename.'. Antes se guardó el estado anterior en '.$pre->filename.'. Si tu sesión se cerró, volvé a ingresar.');
    }
}
