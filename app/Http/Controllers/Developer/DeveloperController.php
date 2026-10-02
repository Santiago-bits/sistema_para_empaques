<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\SystemError;
use App\Services\BackupService;
use App\Services\SystemInfoService;
use App\Support\LogReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Panel del desarrollador (sólo super administrador): estado técnico, errores y logs. */
class DeveloperController extends Controller
{
    public function index(SystemInfoService $info, BackupService $backups): View
    {
        $scheduler = $info->schedulerLastRun();

        return view('developer.index', [
            'version' => $info->version(),
            'health' => $info->health(),
            'server' => $info->server(),
            'environment' => $info->environment(),
            'database' => $info->database(),
            'lastUpdate' => $info->lastUpdate(),
            'queue' => $info->queue(),
            'disk' => $info->disk(),
            'tables' => $info->tableSizes(),
            'modules' => $info->modules(),
            'php' => $info->phpSettings(),
            'missing' => $info->missingExtensions(),
            // OPcache se mide en el proceso web (no en consola): sin él cada pedido recompila el sistema.
            'opcacheOff' => PHP_SAPI !== 'cli' && ! (function_exists('opcache_get_status') && (opcache_get_status(false)['opcache_enabled'] ?? false)),
            'devServer' => PHP_SAPI === 'cli-server',
            'cachesOff' => ! app()->configurationIsCached() || ! app()->routesAreCached(),
            'scheduler' => $scheduler,
            'schedulerStale' => $scheduler === null || $scheduler->lt(now()->subMinutes(5)),
            'backup' => $backups->health(),
            'backupService' => $backups,
            'recentErrors' => SystemError::query()->latest('created_at')->limit(8)->get(['id', 'code', 'category', 'message', 'created_at']),
            'errorsToday' => SystemError::query()->where('created_at', '>=', today())->count(),
        ]);
    }

    public function errors(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:30']]);

        return view('developer.errors', [
            'items' => SystemError::query()->with('user:id,first_name,last_name')
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%'.addcslashes((string) $request->query('q'), '%_\\').'%';
                    $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('message', 'like', $term)->orWhere('url', 'like', $term));
                })
                ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
                ->latest('created_at')->paginate($this->perPage($request))->withQueryString(),
            'categories' => SystemError::query()->distinct()->orderBy('category')->pluck('category', 'category')->all(),
        ]);
    }

    public function error(SystemError $error): View
    {
        return view('developer.error', ['item' => $error->load('user')]);
    }

    public function logs(Request $request): View
    {
        $request->validate(['file' => ['nullable', 'string', 'max:100'], 'lines' => ['nullable', 'integer', 'min:50', 'max:'.LogReader::MAX_LINES]]);
        $path = LogReader::resolve($request->query('file'));
        $lines = (int) $request->query('lines', 300);

        return view('developer.logs', [
            'files' => LogReader::files(),
            'current' => $path ? basename($path) : null,
            'lines' => $lines,
            'content' => $path ? LogReader::tail($path, $lines) : [],
            'size' => $path && is_file($path) ? filesize($path) : 0,
        ]);
    }
}
