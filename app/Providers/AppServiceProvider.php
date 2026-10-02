<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditService;
use App\Services\ModuleService;
use App\Services\SettingsService;
use App\Support\PermissionRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use App\View\Composers\AppLayoutComposer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(ModuleService::class);
        $this->app->scoped(AuditService::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.galpon');

        // Los enlaces absolutos (QR de remitos, emails de recuperación) usan SIEMPRE APP_URL, nunca el
        // encabezado Host del pedido: así nadie puede hacer que un email lleve a una PC ajena.
        if (filled(config('app.url'))) {
            URL::forceRootUrl(rtrim((string) config('app.url'), '/'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Alias estables para relaciones polimórficas (no dependen del namespace).
        Relation::enforceMorphMap([
            'user' => \App\Models\User::class,
            'role' => \App\Models\Role::class,
            'module' => \App\Models\Module::class,
            'setting' => \App\Models\Setting::class,
            'company' => \App\Models\Company::class,
            'warehouse' => \App\Models\Warehouse::class,
            'season' => \App\Models\Season::class,
            'producer' => \App\Models\Producer::class,
            'owner' => \App\Models\Owner::class,
            'client' => \App\Models\Client::class,
            'destination' => \App\Models\Destination::class,
            'provider' => \App\Models\Provider::class,
            'transporter' => \App\Models\Transporter::class,
            'truck' => \App\Models\Truck::class,
            'driver' => \App\Models\Driver::class,
            'variety' => \App\Models\Variety::class,
            'size' => \App\Models\Size::class,
            'shift' => \App\Models\Shift::class,
            'production_line' => \App\Models\ProductionLine::class,
            'production_target' => \App\Models\ProductionTarget::class,
            'packer' => \App\Models\Packer::class,
            'reason' => \App\Models\Reason::class,
            'lot' => \App\Models\Lot::class,
            'location' => \App\Models\WarehouseLocation::class,
            'pallet' => \App\Models\Pallet::class,
            'crate' => \App\Models\Crate::class,
            'production_record' => \App\Models\ProductionRecord::class,
            'quality_control' => \App\Models\QualityControl::class,
            'reject' => \App\Models\Reject::class,
            'stoppage' => \App\Models\ProductionStoppage::class,
            'load' => \App\Models\Load::class,
            'dispatch_check' => \App\Models\DispatchCheck::class,
            'remito' => \App\Models\Remito::class,
            'document' => \App\Models\Document::class,
            'invoice' => \App\Models\Invoice::class,
            'supply' => \App\Models\Supply::class,
            'inventory_movement' => \App\Models\InventoryMovement::class,
            'machine' => \App\Models\Machine::class,
            'maintenance' => \App\Models\Maintenance::class,
            'cold_room' => \App\Models\ColdRoom::class,
            'incident' => \App\Models\Incident::class,
            'daily_closing' => \App\Models\DailyClosing::class,
            'support_ticket' => \App\Models\SupportTicket::class,
            'license' => \App\Models\License::class,
            'cost' => \App\Models\Cost::class,
            'grade' => \App\Models\Grade::class,
            'container_type' => \App\Models\ContainerType::class,
            'crew' => \App\Models\Crew::class,
            'employee' => \App\Models\Employee::class,
            'exchange_rate' => \App\Models\ExchangeRate::class,
            'account_movement' => \App\Models\AccountMovement::class,
            'check' => \App\Models\Check::class,
            'cash_session' => \App\Models\CashSession::class,
            'cash_movement' => \App\Models\CashMovement::class,
            'client_ticket' => \App\Models\ClientTicket::class,
        ]);

        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        $this->registerGates();
        $this->registerRateLimiters();
        $this->registerBladeDirectives();
    }

    private function registerGates(): void
    {
        // Orden de evaluación: módulo desactivado → usuario inactivo → super admin → permiso granular.
        Gate::before(function (User $user, string $ability) {
            $module = PermissionRegistry::moduleOf($ability);

            if ($module !== null && ! app(ModuleService::class)->enabled($module)) {
                return false;
            }
            if (! $user->isActive()) {
                return false;
            }
            if ($user->isSuperAdmin()) {
                return true;
            }
            if ($module !== null) {
                return $user->hasPermission($ability);
            }

            return null; // Lo resuelven las Policies / Gates específicos.
        });

        Gate::define('developer', fn (User $user) => $user->isSuperAdmin());
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = mb_strtolower((string) $request->input('login')).'|'.$request->ip();

            return [Limit::perMinute(5)->by($key), Limit::perMinute(30)->by($request->ip())];
        });

        // Recuperación de contraseña: frena el abuso (spam de emails / avisos) por IP y por usuario pedido.
        RateLimiter::for('password-reset', function (Request $request) {
            $key = mb_strtolower(trim((string) $request->input('login')));

            return [Limit::perMinutes(15, 5)->by('pw|'.$key), Limit::perMinute(10)->by('pw-ip|'.$request->ip())];
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));

        // El modo escaneo puede registrar decenas de cajones por minuto.
        RateLimiter::for('scan', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }

    private function registerBladeDirectives(): void
    {
        Blade::if('module', fn (string $key) => module_enabled($key));
        View::composer('components.layouts.app', AppLayoutComposer::class);
    }
}
