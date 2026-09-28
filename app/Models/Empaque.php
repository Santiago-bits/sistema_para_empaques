<?php

namespace App\Models;

use App\Enums\EstadoEmpaque;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empaque extends Model
{
    /** @use HasFactory<\Database\Factories\EmpaqueFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Caracteres permitidos en el código: sin 0/O, 1/I/L para evitar confusiones al leerlo.
     */
    private const ALFABETO_CODIGO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LARGO_CODIGO = 6;

    /**
     * Solo estos campos se pueden asignar desde un formulario.
     * `codigo` y `user_id` los asigna el sistema, nunca el usuario.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
    ];

    /**
     * Valores por defecto al crear una instancia nueva.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => 'pendiente',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoEmpaque::class,
        ];
    }

    /**
     * Asigna un código único automáticamente al crear el empaque.
     */
    protected static function booted(): void
    {
        static::creating(function (Empaque $empaque) {
            $empaque->codigo ??= static::generarCodigo();
        });
    }

    /**
     * Las rutas buscan el empaque por `codigo` (ej: /empaques/EMP-7K3QX9), no por `id`.
     */
    public function getRouteKeyName(): string
    {
        return 'codigo';
    }

    /**
     * URL absoluta del detalle del empaque: es lo que se codifica en el QR.
     * Usa APP_URL (y no el host de la petición actual) para que el QR
     * apunte siempre al dominio configurado, aunque se genere desde localhost.
     */
    public function urlPublica(): string
    {
        return rtrim(config('app.url'), '/').route('empaques.show', $this, false);
    }

    /**
     * Genera un código con formato EMP-XXXXXX que no exista en la base,
     * incluyendo empaques eliminados, para que un código nunca se reutilice.
     */
    public static function generarCodigo(): string
    {
        do {
            $aleatorio = '';
            for ($i = 0; $i < self::LARGO_CODIGO; $i++) {
                $aleatorio .= self::ALFABETO_CODIGO[random_int(0, strlen(self::ALFABETO_CODIGO) - 1)];
            }
            $codigo = 'EMP-'.$aleatorio;
        } while (static::withTrashed()->where('codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * Filtra por texto en código o nombre. Si el texto está vacío, no filtra.
     */
    public function scopeBuscar(Builder $query, ?string $texto): void
    {
        if (blank($texto)) {
            return;
        }

        $query->where(function (Builder $q) use ($texto) {
            $q->where('codigo', 'like', "%{$texto}%")
                ->orWhere('nombre', 'like', "%{$texto}%");
        });
    }

    /**
     * Filtra por estado. Si el estado es null, no filtra.
     */
    public function scopeConEstado(Builder $query, ?EstadoEmpaque $estado): void
    {
        if ($estado) {
            $query->where('estado', $estado);
        }
    }

    /**
     * Usuario que creó el empaque.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
