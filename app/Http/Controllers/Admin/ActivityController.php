<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserActivityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** «Actividad del personal»: cuánto usa el sistema cada empleado y en qué partes. */
class ActivityController extends Controller
{
    public const PERIODS = [7 => 'Últimos 7 días', 30 => 'Últimos 30 días', 90 => 'Últimos 90 días'];

    public function index(Request $request, UserActivityService $activity): View
    {
        $request->validate(['days' => ['nullable', Rule::in(array_keys(self::PERIODS))]]);
        $days = (int) $request->query('days', 30);

        return view('admin.activity.index', ['days' => $days] + $activity->summary(today()->subDays($days - 1)));
    }
}
