<?php

namespace App\Models\Concerns;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra automáticamente altas, modificaciones (con valor anterior/nuevo),
 * bajas y restauraciones en audit_logs.
 *
 * Un modelo puede excluir campos definiendo `protected array $auditExclude`.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            app(AuditService::class)->log('create', $model, null, $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = $model->auditableAttributes($model->getChanges());
            unset($changes['updated_at'], $changes['version']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            app(AuditService::class)->log('update', $model, $old, $changes);
        });

        static::deleted(function (Model $model) {
            $action = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting() ? 'soft_delete' : 'delete';
            app(AuditService::class)->log($action, $model, $model->auditableAttributes($model->getOriginal()), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                app(AuditService::class)->log('restore', $model);
            });
        }
    }

    public function auditableAttributes(array $attributes): array
    {
        $exclude = array_merge(
            ['password', 'remember_token', 'created_at', 'updated_at'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : []
        );

        return array_diff_key($attributes, array_flip($exclude));
    }
}
