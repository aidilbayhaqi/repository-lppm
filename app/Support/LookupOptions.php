<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LookupOptions
{
    /** Keep initial HTML bounded while preserving selected values outside the first page. */
    public static function initial(Builder $query, string $label, array $selected = []): Collection
    {
        $records = (clone $query)->orderBy($label)->orderBy('id')->limit(25)->get();
        $selected = array_slice(array_values(array_filter($selected, fn ($id) => is_numeric($id) && $id > 0)), 0, 100);
        if ($selected !== []) {
            $records = $records->merge((clone $query)->whereIn('id', $selected)->get());
        }

        return $records->sortBy($label)->values();
    }

    public static function entity(string $model): string
    {
        foreach (ReferenceCatalog::all() as $entity => $definition) {
            if ($definition['model'] === $model) {
                return $entity;
            }
        }
        abort(404);
    }
}
