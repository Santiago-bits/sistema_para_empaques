<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use Auditable;

    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const INTAKE = 'intake_operator';
    public const PACKER = 'packer';
    public const LOADS = 'loads_operator';
    public const QUALITY = 'quality';
    public const BILLING = 'billing';
    public const SUPERVISOR = 'supervisor';
    public const CLIENT = 'client_portal';

    protected $fillable = [
        'slug', 'name', 'description', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Multigalpón: los roles del sistema (sin galpón) los ven todos; los que crea un galpón, sólo ese galpón.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('warehouse', function ($query) {
            if (\App\Support\Tenancy::ready() && ($id = \App\Support\CurrentWarehouse::id()) !== null) {
                $query->where(fn ($q) => $q->whereNull('roles.warehouse_id')->orWhere('roles.warehouse_id', $id));
            }
        });
        static::creating(function (Role $role) {
            if (! $role->is_system && $role->warehouse_id === null && \App\Support\Tenancy::ready()) {
                $role->warehouse_id = \App\Support\CurrentWarehouse::id();
            }
        });
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
