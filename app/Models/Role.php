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

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
