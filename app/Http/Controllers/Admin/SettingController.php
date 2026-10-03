<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sequence;
use App\Services\ModuleService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Configuración general por secciones. Cada sección se guarda por separado
 * y cada cambio queda auditado (modelo Setting usa Auditable).
 */
class SettingController extends Controller
{
    public const GROUPS = [
        'company' => 'Empresa',
        'regional' => 'Regional',
        'production' => 'Producción',
        'fields' => 'Campos',
        'label' => 'Etiquetas',
        'treasury' => 'Tesorería',
        'treatments' => 'Tratamientos',
        'numbering' => 'Numeraciones',
        'alerts' => 'Alertas',
        'security' => 'Seguridad y red',
        'backup' => 'Backups',
        'arca' => 'ARCA',
    ];

    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function index(Request $request): View
    {
        $group = array_key_exists($request->query('tab'), self::GROUPS) ? $request->query('tab') : 'company';

        return view('admin.settings.index', [
            'group' => $group,
            'groups' => self::GROUPS,
            's' => $this->settings->all(),
            'sequences' => Sequence::query()->orderBy('key')->get(),
            'modules' => collect(ModuleService::CATALOG)->map(fn ($m) => $m[0]),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        abort_unless(array_key_exists($group, self::GROUPS), 404);

        $method = 'save'.ucfirst($group);
        $this->{$method}($request);

        return redirect()->route('admin.settings.index', ['tab' => $group])->with('success', 'Configuración guardada.');
    }

    private function saveCompany(Request $request): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'cuit' => ['nullable', 'string', 'max:13'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'locality' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:60'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'iibb' => ['nullable', 'string', 'max:30'],
            'activity_start' => ['nullable', 'date'],
            'website' => ['nullable', 'string', 'max:160'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);
        foreach (['name', 'cuit', 'address', 'phone', 'email', 'legal_name', 'locality', 'province', 'postal_code', 'iibb', 'activity_start', 'website'] as $key) {
            $this->settings->set("company.$key", $data[$key] ?? '');
        }
        $this->settings->set('ui.primary_color', $data['primary_color'] ?? '#16a34a');
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branding', 'public');
            $this->settings->set('company.logo', $path);
        }
    }

    private function saveRegional(Request $request): void
    {
        $data = $request->validate([
            'currency' => ['required', Rule::in(['ARS', 'USD'])],
            'date_format' => ['required', Rule::in(['d/m/Y', 'Y-m-d'])],
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
        ], [], ['timezone' => 'zona horaria']);
        $this->settings->set('regional.currency', $data['currency']);
        $this->settings->set('regional.date_format', $data['date_format']);
        $this->settings->set('regional.timezone', $data['timezone']);
    }

    private function saveProduction(Request $request): void
    {
        // Pesos de un cajón con punto decimal («18.5»); objetivos con punto de miles («10.000»).
        $request->merge([
            'weight_min' => parse_number($request->input('weight_min'), false),
            'weight_max' => parse_number($request->input('weight_max'), false),
            'target_daily_kg' => parse_number($request->input('target_daily_kg')),
            'target_weekly_kg' => parse_number($request->input('target_weekly_kg')),
            'target_monthly_kg' => parse_number($request->input('target_monthly_kg')),
        ]);
        $data = $request->validate([
            'weight_min' => ['required', 'numeric', 'min:0', 'max:1000'],
            'weight_max' => ['required', 'numeric', 'gt:weight_min', 'max:1000'],
            'require_lot' => ['boolean'],
            'auto_create_crate' => ['boolean'],
            'sound_success' => ['boolean'],
            'sound_error' => ['boolean'],
            'sound_duplicate' => ['boolean'],
            'target_daily_kg' => ['required', 'numeric', 'min:0'],
            'target_weekly_kg' => ['required', 'numeric', 'min:0'],
            'target_monthly_kg' => ['required', 'numeric', 'min:0'],
            'scale_driver' => ['required', Rule::in(['manual', 'api'])],
        ]);
        foreach ($data as $key => $value) {
            $this->settings->set("production.$key", $value);
        }
    }

    private function saveFields(Request $request): void
    {
        $data = $request->validate([
            'crate' => ['array'],
            'crate.*' => [Rule::in(['required', 'optional', 'hidden'])],
        ]);
        $allowed = array_keys(\App\Services\CrateService::CONFIGURABLE_FIELDS);
        $config = array_intersect_key($data['crate'] ?? [], array_flip($allowed));
        // El peso nunca puede ocultarse: es la base de producción y facturación.
        if (($config['weight'] ?? 'required') === 'hidden') {
            $config['weight'] = 'required';
        }
        $this->settings->set('fields.crate', array_merge($this->settings->get('fields.crate', []), $config));
    }

    private function saveLabel(Request $request): void
    {
        $request->merge(['nominal_kg' => parse_number($request->input('nominal_kg'), false)]);
        $data = $request->validate([
            'show_company' => ['boolean'],
            'show_regulatory' => ['boolean'],
            'senasa_number' => ['nullable', 'string', 'max:40'],
            'provincial_registry' => ['nullable', 'string', 'max:40'],
            'renspa' => ['nullable', 'string', 'max:40'],
            'decree' => ['nullable', 'string', 'max:60'],
            'origin_legend' => ['nullable', 'string', 'max:60'],
            'nominal_kg' => ['nullable', 'numeric', 'min:0', 'max:2000'],
        ]);
        foreach (['show_company', 'show_regulatory'] as $flag) {
            $this->settings->set("label.$flag", (bool) ($data[$flag] ?? false));
        }
        foreach (['senasa_number', 'provincial_registry', 'renspa', 'decree', 'origin_legend'] as $key) {
            $this->settings->set("label.$key", trim((string) ($data[$key] ?? '')));
        }
        $this->settings->set('label.nominal_kg', (float) ($data['nominal_kg'] ?? 0));
    }

    private function saveTreasury(Request $request): void
    {
        $request->merge(['association_fee_per_kg' => parse_number($request->input('association_fee_per_kg'), false)]);
        $data = $request->validate([
            'association_fee_per_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'post_test_invoices' => ['boolean'],
            'check_warning_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);
        $this->settings->set('treasury.association_fee_per_kg', (float) ($data['association_fee_per_kg'] ?? 0));
        $this->settings->set('treasury.post_test_invoices', (bool) ($data['post_test_invoices'] ?? false));
        $this->settings->set('treasury.check_warning_days', (int) $data['check_warning_days']);
    }

    /** Nombres de los tipos de tratamiento que se ofrecen al cargar uno (p. ej. frío y bromuro). */
    private function saveTreatments(Request $request): void
    {
        $data = $request->validate(['types' => ['array', 'max:6'], 'types.*' => ['nullable', 'string', 'max:60']], [], ['types.*' => 'tipo de tratamiento']);
        $types = array_values(array_unique(array_filter(array_map('trim', $data['types'] ?? []))));
        $this->settings->set('treatments.types', $types ?: \App\Models\Treatment::DEFAULT_TYPES);
    }

    private function saveNumbering(Request $request): void
    {
        $data = $request->validate([
            'sequences' => ['array'],
            'sequences.*.prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\/_]*$/'],
            'sequences.*.padding' => ['required', 'integer', 'min:3', 'max:12'],
            'sequences.*.next_number' => ['required', 'integer', 'min:1'],
        ]);
        foreach ($data['sequences'] ?? [] as $id => $values) {
            $sequence = Sequence::query()->find($id);
            if (! $sequence) {
                continue;
            }
            // Nunca se permite retroceder la numeración: generaría códigos duplicados.
            $next = max((int) $sequence->next_number, (int) $values['next_number']);
            $sequence->update(['prefix' => $values['prefix'] ?? '', 'padding' => $values['padding'], 'next_number' => $next]);
        }
    }

    private function saveAlerts(Request $request): void
    {
        $data = $request->validate([
            'enabled' => ['array'],
            'enabled.*' => ['boolean'],
            'load_pending_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'document_expiring_days' => ['required', 'integer', 'min:1', 'max:365'],
            'crates_unprocessed_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);
        $current = $this->settings->get('alerts.enabled', []);
        $enabled = [];
        foreach (array_keys($current) as $key) {
            $enabled[$key] = (bool) ($data['enabled'][$key] ?? false);
        }
        $this->settings->set('alerts.enabled', $enabled);
        $this->settings->set('alerts.load_pending_hours', $data['load_pending_hours']);
        $this->settings->set('alerts.document_expiring_days', $data['document_expiring_days']);
        $this->settings->set('alerts.crates_unprocessed_hours', $data['crates_unprocessed_hours']);
    }

    private function saveSecurity(Request $request): void
    {
        $data = $request->validate([
            'login_identifiers' => ['required', 'array', 'min:1'],
            'login_identifiers.*' => [Rule::in(['username', 'dni', 'cuit', 'internal_code', 'email'])],
            'lan_only_modules' => ['array'],
            'lan_only_modules.*' => [Rule::in(array_keys(ModuleService::CATALOG))],
            'allowed_ranges' => ['nullable', 'string', 'max:2000'],
        ]);
        $ranges = collect(preg_split('/[\s,]+/', (string) ($data['allowed_ranges'] ?? '')))
            ->filter()
            ->filter(fn ($r) => preg_match('/^[0-9a-fA-F:.]+(\/\d{1,3})?$/', $r))
            ->values()->all();

        $this->settings->set('login.identifiers', array_values($data['login_identifiers']));
        $this->settings->set('network.lan_only_modules', array_values($data['lan_only_modules'] ?? []));
        $this->settings->set('network.allowed_ranges', $ranges ?: ['127.0.0.1/32']);
    }

    private function saveBackup(Request $request): void
    {
        $data = $request->validate([
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'daily' => ['boolean'],
            'weekly' => ['boolean'],
        ]);
        foreach ($data as $key => $value) {
            $this->settings->set("backup.$key", $value);
        }
    }

    private function saveArca(Request $request): void
    {
        abort_unless($request->user()->can('arca.manage'), 403);
        $data = $request->validate([
            'mode' => ['required', Rule::in(['simulation', 'homologation', 'production'])],
            'point_of_sale' => ['required', 'integer', 'min:1', 'max:99999'],
            'cuit' => ['nullable', 'digits:11'],
            'emitter_condition' => ['required', Rule::in(['RI', 'MT'])],
        ]);
        foreach ($data as $key => $value) {
            $this->settings->set("arca.$key", $value);
        }
    }
}
