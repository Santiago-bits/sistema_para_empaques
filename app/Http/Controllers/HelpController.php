<?php

namespace App\Http\Controllers;

use App\Support\Menu;
use App\Support\Shortcuts;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** «Ayuda y atajos»: lista de atajos de teclado según lo que el usuario puede abrir. */
class HelpController extends Controller
{
    public function shortcuts(Request $request): View
    {
        return view('help.shortcuts', [
            'groups' => Shortcuts::for(Menu::for($request->user()))['groups'],
        ]);
    }
}
