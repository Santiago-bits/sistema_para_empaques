<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Búsqueda global. Un código exacto (escaneado) abre directo la ficha. */
class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search): View|RedirectResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) $request->query('q'));

        if ($term !== '' && ! $request->boolean('all') && ($url = $search->exactMatch($request->user(), $term))) {
            return redirect()->to($url);
        }

        return view('search.index', [
            'term' => $term,
            'groups' => $search->search($request->user(), $term),
            'tooShort' => $term !== '' && mb_strlen($term) < SearchService::MIN_LENGTH,
        ]);
    }
}
