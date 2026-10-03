<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Ficha de la app instalable (PWA): con ella Chrome/Edge ofrecen «Instalar» y el sistema queda con ícono en el
 * escritorio, en el menú Inicio o en el celular, y se abre en su propia ventana. Lleva el nombre de la empresa.
 */
class AppManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $name = (string) setting('company.name', 'Galpón de Empaque');
        } catch (\Throwable) {
            $name = 'Galpón de Empaque';
        }
        $name = $name !== '' ? $name : 'Galpón de Empaque';

        return response()->json([
            'name' => $name,
            'short_name' => mb_strlen($name) > 14 ? 'Galpón' : $name,
            'description' => 'Gestión del galpón de empaque: producción, cajones, pallets, cargas, facturación y tesorería.',
            'lang' => 'es-AR',
            'dir' => 'ltr',
            'id' => '/',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone'],
            'orientation' => 'any',
            'background_color' => '#0c0a09',
            'theme_color' => '#16a34a',
            'categories' => ['business', 'productivity'],
            'icons' => [
                ['src' => '/icons/icon-192.png?v=2', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png?v=2', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png?v=2', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Escanear cajones', 'url' => '/produccion/escaneo', 'icons' => [['src' => '/icons/icon-192.png?v=2', 'sizes' => '192x192']]],
                ['name' => 'Cargas', 'url' => '/cargas', 'icons' => [['src' => '/icons/icon-192.png?v=2', 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }
}
