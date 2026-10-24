<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait SearchString
{
    public function scopeUsingSearchString(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);
        if ($search === '') {
            return $query;
        }

        $columns = collect($this->searchStringColumns ?? [])
            ->map(fn ($options, $column) => is_int($column) ? $options : $column)
            ->filter(fn ($column) => is_string($column))
            ->values();

        return $query->where(function (Builder $query) use ($columns, $search) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'LIKE', "%$search%");
            }
        });
    }
}
