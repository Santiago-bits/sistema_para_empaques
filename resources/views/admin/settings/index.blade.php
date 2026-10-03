<x-layouts.app title="Configuración">
    <x-page-header title="Configuración" subtitle="Parámetros generales del galpón. Cada cambio queda registrado en auditoría."/>

    <div class="flex flex-col gap-6 lg:flex-row">
        <nav class="flex shrink-0 gap-1 overflow-x-auto lg:w-52 lg:flex-col">
            @foreach ($groups as $key => $label)
                <a href="{{ route('admin.settings.index', ['tab' => $key]) }}"
                   @class(['rounded-lg px-3 py-2 text-sm whitespace-nowrap', 'bg-white font-medium shadow-sm ring-1 ring-stone-200 dark:bg-stone-900 dark:ring-stone-800' => $group === $key, 'text-stone-600 hover:bg-white/60 dark:text-stone-400 dark:hover:bg-stone-900/60' => $group !== $key])>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('admin.settings.update', $group) }}" enctype="multipart/form-data" class="min-w-0 flex-1 space-y-6">
            @csrf
            <x-panel :title="$groups[$group]">
                @switch($group)
                    @case('company')
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-input name="name" label="Nombre del galpón" :value="$s['company.name']" required/>
                            <x-input name="legal_name" label="Razón social" :value="$s['company.legal_name']"/>
                            <x-input name="cuit" label="CUIT" :value="$s['company.cuit']"/>
                            <x-input name="iibb" label="Ingresos Brutos N°" :value="$s['company.iibb']"/>
                            <x-input name="activity_start" type="date" label="Inicio de actividades" :value="$s['company.activity_start']"/>
                            <x-input name="address" label="Dirección" :value="$s['company.address']"/>
                            <x-input name="locality" label="Localidad" :value="$s['company.locality']"/>
                            <x-input name="province" label="Provincia" :value="$s['company.province']"/>
                            <x-input name="postal_code" label="Código postal" :value="$s['company.postal_code']"/>
                            <x-input name="phone" label="Teléfono" :value="$s['company.phone']"/>
                            <x-input name="email" type="email" label="Email" :value="$s['company.email']"/>
                            <x-input name="website" label="Sitio web" :value="$s['company.website']"/>
                            <x-input name="primary_color" type="color" label="Color principal" :value="$s['ui.primary_color']" class="h-10 p-1"/>
                            <x-field label="Logo" name="logo" hint="PNG/JPG, máximo 1 MB.">
                                <input type="file" name="logo" accept="image/*" class="form-input">
                            </x-field>
                            @if ($s['company.logo'])
                                <img src="{{ asset('storage/'.$s['company.logo']) }}" alt="Logo" class="h-16 rounded border border-stone-200 object-contain p-1 dark:border-stone-700">
                            @endif
                        </div>
                        @break

                    @case('regional')
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-select name="currency" label="Moneda" :options="['ARS' => 'Peso argentino (ARS)', 'USD' => 'Dólar (USD)']" :value="$s['regional.currency']"/>
                            <x-select name="date_format" label="Formato de fecha" :options="['d/m/Y' => '30/09/2026', 'Y-m-d' => '2026-09-30']" :value="$s['regional.date_format']"/>
                            <x-field label="Zona horaria" name="timezone" hint="Hora con la que se registran y muestran los datos. Cambiala sólo si el galpón está en otro país o provincia.">
                                <select name="timezone" id="timezone" class="form-input">
                                    @foreach (\App\Http\Controllers\InstallController::timezones() as $region => $options)
                                        <optgroup label="{{ $region }}">
                                            @foreach ($options as $value => $label)
                                                <option value="{{ $value }}" @selected(old('timezone', $s['regional.timezone']) === $value && ($region === 'Frecuentes' || ! in_array($value, array_keys(\App\Http\Controllers\InstallController::timezones()['Frecuentes']), true)))>{{ $label }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </x-field>
                        </div>
                        @break

                    @case('production')
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-input name="weight_min" inputmode="decimal" label="Peso mínimo (kg)" :value="$s['production.weight_min']" required/>
                            <x-input name="weight_max" inputmode="decimal" label="Peso máximo (kg)" :value="$s['production.weight_max']" required
                                     hint="Fuera de rango requiere autorización de supervisor."/>
                            <x-select name="scale_driver" label="Origen del peso" :options="['manual' => 'Manual (teclado)', 'api' => 'Balanza vía API']" :value="$s['production.scale_driver']"/>
                            <x-input name="target_daily_kg" inputmode="decimal" hint="Ej.: 10.000" label="Objetivo diario (kg)" :value="$s['production.target_daily_kg']" required/>
                            <x-input name="target_weekly_kg" inputmode="decimal" hint="Ej.: 10.000" label="Objetivo semanal (kg)" :value="$s['production.target_weekly_kg']" required/>
                            <x-input name="target_monthly_kg" inputmode="decimal" hint="Ej.: 10.000" label="Objetivo mensual (kg)" :value="$s['production.target_monthly_kg']" required/>
                        </div>
                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                            <x-checkbox name="auto_create_crate" label="Crear el cajón al escanear un código nuevo" :checked="$s['production.auto_create_crate']"
                                        hint="Si está desactivado, el cajón debe existir previamente (precargado o etiquetado)."/>
                            <x-checkbox name="require_lot" label="Lote obligatorio en producción" :checked="$s['production.require_lot']"/>
                            <x-checkbox name="sound_success" label="Sonido de registro correcto" :checked="$s['production.sound_success']"/>
                            <x-checkbox name="sound_error" label="Sonido de error" :checked="$s['production.sound_error']"/>
                            <x-checkbox name="sound_duplicate" label="Sonido de cajón duplicado" :checked="$s['production.sound_duplicate']"/>
                        </div>
                        @break

                    @case('fields')
                        <p class="mb-4 text-sm text-stone-500">Definí qué campos del cajón son obligatorios, opcionales u ocultos.</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach (\App\Services\CrateService::CONFIGURABLE_FIELDS as $field => $label)
                                <x-select :name="'crate['.$field.']'" :label="$label" :value="$s['fields.crate'][$field] ?? 'optional'"
                                          :options="$field === 'weight' ? ['required' => 'Obligatorio'] : ['required' => 'Obligatorio', 'optional' => 'Opcional', 'hidden' => 'Oculto']"/>
                            @endforeach
                        </div>
                        @break

                    @case('numbering')
                        <p class="mb-4 text-sm text-stone-500">La numeración nunca puede retroceder (evita códigos duplicados).</p>
                        <table class="table">
                            <thead><tr><th>Documento</th><th>Prefijo</th><th>Dígitos</th><th>Próximo número</th><th>Ejemplo</th></tr></thead>
                            <tbody>
                                @foreach ($sequences as $seq)
                                    <tr>
                                        <td class="font-medium">{{ __('sequences.'.$seq->key) }}</td>
                                        <td><input name="sequences[{{ $seq->id }}][prefix]" value="{{ $seq->prefix }}" class="form-input w-28 code"></td>
                                        <td><input type="number" min="3" max="12" name="sequences[{{ $seq->id }}][padding]" value="{{ $seq->padding }}" class="form-input w-20"></td>
                                        <td><input type="number" min="{{ $seq->next_number }}" name="sequences[{{ $seq->id }}][next_number]" value="{{ $seq->next_number }}" class="form-input w-32"></td>
                                        <td class="code text-stone-500">{{ $seq->prefix.str_pad((string) $seq->next_number, $seq->padding, '0', STR_PAD_LEFT) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @break

                    @case('alerts')
                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach ($s['alerts.enabled'] as $key => $enabled)
                                <x-checkbox :name="'enabled['.$key.']'" :label="__('alerts.types.'.$key)" :checked="$enabled"/>
                            @endforeach
                        </div>
                        <div class="mt-5 grid gap-4 md:grid-cols-3">
                            <x-input name="load_pending_hours" type="number" label="Carga pendiente después de (horas)" :value="$s['alerts.load_pending_hours']"/>
                            <x-input name="crates_unprocessed_hours" type="number" label="Cajones sin procesar después de (horas)" :value="$s['alerts.crates_unprocessed_hours']"/>
                            <x-input name="document_expiring_days" type="number" label="Avisar vencimientos con (días)" :value="$s['alerts.document_expiring_days']"/>
                        </div>
                        @break

                    @case('security')
                        <x-field label="Identificadores válidos para iniciar sesión">
                            <div class="mt-1 flex flex-wrap gap-4">
                                @foreach (['username' => 'Usuario', 'dni' => 'DNI', 'cuit' => 'CUIT', 'internal_code' => 'Código interno', 'email' => 'Email'] as $key => $label)
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="login_identifiers[]" value="{{ $key }}" @checked(in_array($key, $s['login.identifiers'], true))
                                               class="size-4 rounded border-stone-300 text-brand-600"> {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </x-field>
                        <x-field label="Módulos restringidos a la red local" class="mt-5" hint="Sólo podrán usarse desde IPs dentro de los rangos permitidos.">
                            <div class="mt-1 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                                @foreach ($modules as $key => $name)
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="lan_only_modules[]" value="{{ $key }}" @checked(in_array($key, $s['network.lan_only_modules'], true))
                                               class="size-4 rounded border-stone-300 text-brand-600"> {{ $name }}
                                    </label>
                                @endforeach
                            </div>
                        </x-field>
                        <x-textarea name="allowed_ranges" label="Rangos de red permitidos (CIDR)" class="code mt-5"
                                    :value="implode(PHP_EOL, $s['network.allowed_ranges'])" hint="Uno por línea. Ej.: 192.168.1.0/24"/>
                        @break

                    @case('backup')
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-input name="retention_days" type="number" label="Retención (días)" :value="$s['backup.retention_days']"/>
                        </div>
                        <div class="mt-4 space-y-2">
                            <x-checkbox name="daily" label="Copia de seguridad automática todos los días (03:00)" :checked="$s['backup.daily']"/>
                            <x-checkbox name="weekly" label="Copia de seguridad automática semanal (domingo 04:00)" :checked="$s['backup.weekly']"/>
                        </div>
                        @break

                    @case('label')
                        <p class="mb-4 text-sm text-stone-500">Datos que se imprimen en la etiqueta de cada cajón o caja, como en el envase: inscripción SENASA, registro provincial, RENSPA y leyendas legales.</p>
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-input name="senasa_number" label="Inscripción SENASA" :value="$s['label.senasa_number']" placeholder="E-1234" hint="Se imprime «SENASA E-…»."/>
                            <x-input name="provincial_registry" label="Reg. Provincial de Empaque N°" :value="$s['label.provincial_registry']"/>
                            <x-input name="renspa" label="RENSPA" :value="$s['label.renspa']" placeholder="00.000.0.00000/00"/>
                            <x-input name="decree" label="Norma" :value="$s['label.decree']"/>
                            <x-input name="origin_legend" label="Leyenda de origen" :value="$s['label.origin_legend']"/>
                            <x-input name="nominal_kg" inputmode="decimal" label="Kg aprox. por envase" :value="$s['label.nominal_kg'] ?: null" hint="Si el cajón no tiene peso, se imprime «Kg aprox.» con este valor."/>
                        </div>
                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                            <x-checkbox name="show_company" label="Imprimir nombre, CUIT y dirección del empaque" :checked="$s['label.show_company']"/>
                            <x-checkbox name="show_regulatory" label="Imprimir los datos oficiales (SENASA, registro, RENSPA, norma y origen)" :checked="$s['label.show_regulatory']"/>
                        </div>
                        @break

                    @case('treasury')
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-input name="association_fee_per_kg" inputmode="decimal" label="Tasa de asociación ($ por kg)" :value="$s['treasury.association_fee_per_kg'] ?: null"
                                     hint="Se descuenta al productor al liquidar la compra de fruta. Vacío o 0 = no se cobra."/>
                            <x-input name="check_warning_days" type="number" min="1" max="90" label="Avisar cheques que vencen en (días)" :value="$s['treasury.check_warning_days']" required/>
                        </div>
                        <div class="mt-5">
                            <x-checkbox name="post_test_invoices" label="Imputar en cuenta corriente también los comprobantes de prueba (simulación / homologación)" :checked="$s['treasury.post_test_invoices']"
                                        hint="Dejalo desactivado en producción: así las facturas de prueba no generan deudas falsas a los clientes."/>
                        </div>
                        @break

                    @case('arca')
                        <div class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            Los certificados y claves de ARCA se configuran sólo en el archivo <span class="code">.env</span> del servidor
                            (ARCA_CERT_PATH, ARCA_KEY_PATH). Nunca se guardan en la base de datos ni se muestran en pantalla.
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-select name="mode" label="Modo" :value="$s['arca.mode']" :options="collect(\App\Enums\ArcaMode::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])"/>
                            <x-input name="point_of_sale" type="number" label="Punto de venta" :value="$s['arca.point_of_sale']"/>
                            <x-input name="cuit" label="CUIT emisor" :value="$s['arca.cuit']" hint="11 dígitos"/>
                            <x-select name="emitter_condition" label="Condición del emisor" :value="$s['arca.emitter_condition']"
                                      :options="['RI' => 'Responsable Inscripto (Factura A / B)', 'MT' => 'Monotributo (Factura C)']"/>
                        </div>
                        @break
                @endswitch
            </x-panel>

            <div class="flex justify-end">
                <button class="btn btn-primary">Guardar {{ mb_strtolower($groups[$group]) }}</button>
            </div>
        </form>
    </div>
</x-layouts.app>
