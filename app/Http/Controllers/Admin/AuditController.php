<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user:id,first_name,last_name,username')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('type'), fn ($q) => $q->where('auditable_type', $request->string('type')))
            ->when($request->filled('id'), fn ($q) => $q->where('auditable_id', $request->integer('id')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest('created_at')->latest('id')
            ->paginate($this->perPage($request, 50))
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'users' => User::query()->orderBy('last_name')->get()->mapWithKeys(fn ($u) => [$u->id => $u->full_name.' ('.$u->username.')']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action'),
            'types' => AuditLog::query()->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type', 'auditable_type'),
        ]);
    }

    public function show(AuditLog $log): View
    {
        return view('admin.audit.show', ['log' => $log->load('user')]);
    }
}
