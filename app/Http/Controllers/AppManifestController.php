<?php

namespace App\Http\Controllers;

use App\Services\BrandingService;
use Illuminate\Http\JsonResponse;

/**
 * Ficha de la app instalable (PWA): con ella Chrome/Edge ofrecen «Instalar» y el sistema queda con ícono en el
 * escritorio, en el menú Inicio o en el celular, y se abre en su propia ventana. Lleva el nombre de la empresa,
 * su color principal y, si se subió un logo, íconos hechos con ese logo.
 */
class AppManifestController extends Controller
{
    public function __invoke(BrandingService $branding): JsonResponse
    {
        // Antes de instalar puede no haber base: nombre, color e íconos de siempre.
        try {
            $name = (string) setting('company.name', 'Galpón de Empaque');
            $color = (string) setting('ui.primary_color', '#16a34a');
            $icon = fn (string $n) => $branding->iconUrl($n);
        } catch (\Throwable) {
            $name = 'Galpón de Empaque';
            $color = '#16a34a';
            $icon = fn (string $n) => asset('icons/'.$n.'.png').'?v=2';
        }
        $name = $name !== '' ? $name : 'Galpón de Empaque';
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : '#16a34a';

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
            'theme_color' => $color,
            'categories' => ['business', 'productivity'],
            'icons' => [
                ['src' => $icon('icon-192'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon('icon-512'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon('icon-maskable-512'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Escanear cajones', 'url' => '/produccion/escaneo', 'icons' => [['src' => $icon('icon-192'), 'sizes' => '192x192']]],
                ['name' => 'Cargas', 'url' => '/cargas', 'icons' => [['src' => $icon('icon-192'), 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }
}
