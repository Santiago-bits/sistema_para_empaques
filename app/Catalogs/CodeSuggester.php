<?php

namespace App\Catalogs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sugiere el próximo código correlativo de un catálogo (EMB001, EMB002, PRD001...).
 * Es sólo una sugerencia editable: la unicidad la garantiza la validación + índice único.
 */
class CodeSuggester
{
    /** @param  class-string<Model>  $model */
    public static function next(string $model, string $prefix, int $padding = 3, string $column = 'code'): string
    {
        $query = $model::query();
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $query->withTrashed();
        }

        $max = 0;
        $pattern = '/^'.preg_quote($prefix, '/').'(\d+)$/';
        $query->where($column, 'like', $prefix.'%')->pluck($column)->each(function ($code) use ($pattern, &$max) {
            if (preg_match($pattern, (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        });

        return $prefix.str_pad((string) ($max + 1), $padding, '0', STR_PAD_LEFT);
    }
}
