<?php

namespace App\Services;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogRegistry;
use Illuminate\Support\Str;

/**
 * Decide a qué sección va una planilla (productores, camioneros, camiones…) mirando el nombre del archivo
 * y los encabezados de sus columnas.
 *
 * Regla de oro: ante la duda NO se elige. Si las columnas sirven para más de una sección (productores y
 * propietarios tienen casi las mismas) o el nombre del archivo dice una cosa y las columnas otra, la
 * planilla queda «sin reconocer» y el usuario elige a mano. Así nunca se mezclan datos entre secciones.
 */
class ImportDetector
{
    /** Palabras extra del nombre del archivo que indican cada sección (además del título y el singular). */
    private const SYNONYMS = [
        'producers' => ['productor', 'productores', 'quinta', 'quintas', 'chacra', 'chacras'],
        'owners' => ['propietario', 'propietarios', 'dueno', 'duenos'],
        'clients' => ['cliente', 'clientes'],
        'destinations' => ['destino', 'destinos'],
        'providers' => ['proveedor', 'proveedores'],
        'transporters' => ['transportista', 'transportistas', 'transporte', 'transportes', 'fletero', 'fleteros'],
        'trucks' => ['camion', 'camiones', 'vehiculo', 'vehiculos', 'patentes', 'acoplado', 'acoplados'],
        'drivers' => ['camionero', 'camioneros', 'chofer', 'choferes', 'conductor', 'conductores'],
        'varieties' => ['variedad', 'variedades', 'producto', 'productos', 'especie', 'especies', 'fruta', 'frutas'],
        'sizes' => ['tamano', 'tamanos', 'calibre', 'calibres'],
        'grades' => ['seleccion', 'selecciones'],
        'containers' => ['envase', 'envases', 'bin', 'bines'],
        'packers' => ['embalador', 'embaladores', 'embaladora', 'embaladoras'],
        'crews' => ['cuadrilla', 'cuadrillas'],
        'employees' => ['empleado', 'empleados', 'personal', 'operario', 'operarios', 'legajos'],
    ];

    /** Palabras que delatan una planilla de DTV-e (se importa desde Logística → DTV-e, que la entiende entera). */
    private const DTV_WORDS = ['dtv', 'dtve', 'dtv_e'];

    public function __construct(private readonly ImportService $imports)
    {
    }

    /**
     * @return array{type: ?string, special: ?string, reason: string, candidates: list<string>, headers: list<string>}
     */
    public function detect(string $absolutePath, string $filename): array
    {
        $headers = $this->headers($absolutePath);
        $tokens = $this->tokens(pathinfo($filename, PATHINFO_FILENAME));
        $result = fn (?string $type, string $reason, array $candidates = [], ?string $special = null) => [
            'type' => $type, 'special' => $special, 'reason' => $reason, 'candidates' => array_values($candidates), 'headers' => $headers,
        ];

        if ($this->isDtv($tokens, $headers)) {
            return $result(null, 'Es una planilla de DTV-e: se importa desde Logística → DTV-e.', [], 'dtv');
        }
        if ($headers === []) {
            return $result(null, 'La planilla está vacía o no tiene encabezados en la primera fila.');
        }

        $definitions = CatalogRegistry::importable();
        $columns = array_map(fn (CatalogDefinition $d) => $this->columnsOf($d), $definitions);

        // Puntaje de cada sección: columnas que coinciden menos columnas que son de OTRA sección.
        $scores = [];
        foreach ($definitions as $type => $definition) {
            $matched = array_values(array_intersect($headers, $columns[$type]));
            $foreign = array_values(array_filter($headers, fn ($h) => ! in_array($h, $columns[$type], true)
                && collect($columns)->except($type)->contains(fn ($cols) => in_array($h, $cols, true))));
            $scores[$type] = ['matched' => $matched, 'foreign' => $foreign, 'score' => count($matched) - count($foreign)];
        }
        $eligible = array_filter($scores, fn ($s) => count($s['matched']) >= 2 && count($s['matched']) > count($s['foreign']));
        uasort($eligible, fn ($a, $b) => $b['score'] <=> $a['score']);
        $label = fn (string $type) => $definitions[$type]->title();

        // 1) El nombre del archivo dice la sección: se usa sólo si las columnas también corresponden.
        $hinted = $this->typesFromName($tokens, $definitions);
        if (count($hinted) > 1) {
            return $result(null, 'El nombre del archivo nombra varias secciones ('.implode(', ', array_map($label, $hinted)).'). Elegí a cuál va.', $hinted);
        }
        if (count($hinted) === 1) {
            $hint = $hinted[0];
            if (! isset($eligible[$hint])) {
                $looksLike = $eligible === [] ? '' : ' (se parecen más a «'.$label(array_key_first($eligible)).'»)';

                return $result(null, 'El nombre dice «'.$label($hint).'», pero las columnas no son las de esa sección'.$looksLike.'. Revisá la planilla o elegí a mano.',
                    array_unique(array_merge([$hint], array_keys($eligible))));
            }
            $better = array_filter($eligible, fn ($s, $type) => $type !== $hint && $s['score'] > $eligible[$hint]['score'] + 1, ARRAY_FILTER_USE_BOTH);
            if ($better !== []) {
                return $result(null, 'El nombre dice «'.$label($hint).'», pero las columnas se parecen más a «'.$label(array_key_first($better)).'». Elegí a cuál va.',
                    array_unique(array_merge([$hint], array_keys($better))));
            }

            return $result($hint, 'Por el nombre del archivo y sus columnas ('.$this->list($eligible[$hint]['matched']).').');
        }

        // 2) Sin pista en el nombre: sólo si una sección gana claramente y tiene alguna columna que es únicamente suya.
        if ($eligible === []) {
            return $result(null, 'Las columnas no coinciden con ninguna sección. Usá los encabezados de la planilla modelo.');
        }
        $ranked = array_keys($eligible);
        $best = $ranked[0];
        $tied = array_values(array_filter($ranked, fn ($type) => $eligible[$type]['score'] === $eligible[$best]['score']));
        if (count($tied) > 1) {
            return $result(null, 'Las columnas sirven para varias secciones ('.implode(', ', array_map($label, $tied)).'). Elegí a cuál va.', $tied);
        }
        $own = array_values(array_filter($eligible[$best]['matched'], fn ($h) => ! collect($columns)->except($best)->contains(fn ($cols) => in_array($h, $cols, true))));
        if ($own === []) {
            return $result(null, 'Las columnas son comunes a varias secciones ('.implode(', ', array_map($label, array_slice($ranked, 0, 3))).'). Elegí a cuál va.', array_slice($ranked, 0, 3));
        }

        return $result($best, 'Por sus columnas (sobre todo '.$this->list($own).').');
    }

    /** @return list<string> Encabezados normalizados de la primera fila con datos. */
    public function headers(string $absolutePath): array
    {
        foreach ($this->imports->readRows($absolutePath) as $cells) {
            return array_values(array_unique(array_filter(array_map(fn ($c) => $this->imports->normalizeHeader((string) $c), $cells))));
        }

        return [];
    }

    /** @return list<string> */
    private function columnsOf(CatalogDefinition $definition): array
    {
        $columns = [];
        foreach ($definition->importColumns() as $header => $field) {
            $columns[] = $this->imports->normalizeHeader($header);
            $columns[] = $this->imports->normalizeHeader($field);
        }
        // «activo» lo aceptan todas: no ayuda a distinguir.
        return array_values(array_diff(array_unique($columns), ['activo', 'active']));
    }

    /** @return list<string> */
    private function typesFromName(array $tokens, array $definitions): array
    {
        $found = [];
        foreach ($definitions as $type => $definition) {
            $words = array_merge(self::SYNONYMS[$type] ?? [], [$this->imports->normalizeHeader($definition->singular())]);
            $title = $this->imports->normalizeHeader($definition->title());
            if (array_intersect($tokens, $words) !== [] || ($title !== '' && str_contains('_'.implode('_', $tokens).'_', '_'.$title.'_'))) {
                $found[] = $type;
            }
        }

        return $found;
    }

    /** @return list<string> */
    private function tokens(string $name): array
    {
        return array_values(array_filter(explode('_', $this->imports->normalizeHeader($name))));
    }

    private function isDtv(array $tokens, array $headers): bool
    {
        if (array_intersect($tokens, self::DTV_WORDS) !== []) {
            return true;
        }
        foreach ($headers as $header) {
            if (Str::contains($header, 'dtv')) {
                return true;
            }
        }

        return false;
    }

    private function list(array $headers): string
    {
        return implode(', ', array_slice($headers, 0, 4));
    }
}
