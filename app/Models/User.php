<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Auditable, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'username', 'dni', 'cuit', 'internal_code', 'email', 'phone',
        'role_id', 'packer_id', 'owner_id', 'client_id', 'status', 'theme', 'kiosk_mode',
        'deactivated_at', 'notes', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** Permisos efectivos memorizados por instancia (una request). */
    private ?Collection $resolvedPermissions = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'kiosk_mode' => 'boolean',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function packer(): BelongsTo
    {
        return $this->belongsTo(Packer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withPivot('granted');
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouse');
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === Role::SUPER_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Permisos efectivos = permisos del rol + concedidos individualmente − revocados individualmente.
     *
     * @return Collection<int, string>
     */
    public function permissionSlugs(): Collection
    {
        if ($this->resolvedPermissions !== null) {
            return $this->resolvedPermissions;
        }

        $fromRole = $this->role ? $this->role->permissions()->pluck('slug') : collect();
        $overrides = $this->permissionOverrides()->get(['permissions.slug']);
        $granted = $overrides->filter(fn ($p) => (bool) $p->pivot->granted)->pluck('slug');
        $revoked = $overrides->reject(fn ($p) => (bool) $p->pivot->granted)->pluck('slug');

        return $this->resolvedPermissions = $fromRole->merge($granted)->unique()->diff($revoked)->values();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissionSlugs()->contains($slug);
    }

    public function flushPermissionCache(): void
    {
        $this->resolvedPermissions = null;
    }
}
