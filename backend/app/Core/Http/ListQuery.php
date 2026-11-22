<?php

namespace App\Core\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Helper standar untuk endpoint list: ?search=&sort=&direction=&per_page=&page=
 */
class ListQuery
{
    public const MAX_PER_PAGE = 100;

    /**
     * @param  list<string>  $searchable  kolom yang dicari dengan ILIKE
     * @param  list<string>  $sortable  kolom yang boleh dipakai untuk sort
     */
    public static function paginate(
        Builder $query,
        Request $request,
        array $searchable = [],
        array $sortable = [],
        string $defaultSort = 'id',
        string $defaultDirection = 'asc',
    ): LengthAwarePaginator {
        $search = trim((string) $request->query('search', ''));
        if ($search !== '' && $searchable) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function (Builder $q) use ($searchable, $term) {
                foreach ($searchable as $column) {
                    $q->orWhere($column, 'ilike', $term);
                }
            });
        }

        $sort = (string) $request->query('sort', $defaultSort);
        if (! in_array($sort, [...$sortable, $defaultSort], true)) {
            $sort = $defaultSort;
        }
        $direction = strtolower((string) $request->query('direction', $defaultDirection)) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction);
        if ($sort !== 'id') {
            $query->orderBy($query->getModel()->getQualifiedKeyName());
        }

        $perPage = max(1, min(self::MAX_PER_PAGE, (int) $request->query('per_page', 20)));

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Filter arsip: ?active=1 (default), ?active=0 (arsip), ?active=all.
     */
    public static function filterActive(Builder $query, Request $request, string $column = 'is_active'): Builder
    {
        $active = $request->query('active', '1');

        return match ((string) $active) {
            'all' => $query,
            '0', 'false' => $query->where($column, false),
            default => $query->where($column, true),
        };
    }
}
