<?php

declare(strict_types=1);

namespace Lalaz\Orm\Relations;

use DateTimeImmutable;
use Lalaz\Orm\Model;

final class BelongsToMany extends Relation
{
    public function getParentKey(): string
    {
        return $this->parentKey;
    }

    public function __construct(
        Model $related,
        private string $table,
        private string $foreignPivotKey,
        private string $relatedPivotKey,
        private string $parentKey,
        private string $relatedKey,
        private Model $parent,
        private array $pivotColumns = ['created_at', 'updated_at'],
    ) {
        parent::__construct($related);
    }

    /**
     * Attach related IDs to the pivot table.
     *
     * @param array<int, mixed>|int|string $ids
     * @param array<string, mixed> $attributes
     */
    public function attach(array|int|string $ids, array $attributes = []): void
    {
        $pairs = $this->normalizeIds($ids, $attributes);
        $timestamp = $this->now();
        $parentId = $this->parent->getAttribute($this->parentKey);

        if ($parentId === null) {
            throw new \InvalidArgumentException(
                'Cannot attach without a parent key value.',
            );
        }

        $rows = [];
        foreach ($pairs as $id => $attrs) {
            $rows[] = $this->pivotPayload((string) $id, $attrs, $timestamp);
        }

        if ($rows !== []) {
            $this->parent->getConnection()->table($this->table)->insert($rows);
        }

        $this->parent->forgetRelationCache($this->relationName());
    }

    /**
     * Detach related IDs from the pivot table.
     *
     * @param array<int, mixed>|int|string|null $ids
     */
    public function detach(array|int|string|null $ids = null): int
    {
        $parentId = $this->parent->getAttribute($this->parentKey);
        if ($parentId === null) {
            return 0;
        }

        $builder = $this->parent
            ->getConnection()
            ->table($this->table)
            ->where($this->foreignPivotKey, $parentId);

        if ($ids !== null) {
            $builder->whereIn(
                $this->relatedPivotKey,
                is_array($ids) ? $ids : [$ids],
            );
        }

        $deleted = (int) $builder->delete();
        $this->parent->forgetRelationCache($this->relationName());

        return $deleted;
    }

    /**
     * Sync the pivot table with the given IDs/attributes.
     *
     * @param array<int, mixed>|array<string|int, array<string, mixed>> $ids
     * @param bool $detaching
     */
    public function sync(array $ids, bool $detaching = true): void
    {
        $pairs = $this->normalizeIds($ids, []);
        $parentId = $this->parent->getAttribute($this->parentKey);

        if ($parentId === null) {
            throw new \InvalidArgumentException(
                'Cannot sync without a parent key value.',
            );
        }

        $existing = $this->parent
            ->getConnection()
            ->table($this->table)
            ->where($this->foreignPivotKey, $parentId)
            ->pluck($this->relatedPivotKey);

        $existingKeys = array_map('strval', $existing);
        $targetKeys = array_map('strval', array_keys($pairs));

        $toDetach = $detaching ? array_diff($existingKeys, $targetKeys) : [];
        $toAttach = array_diff($targetKeys, $existingKeys);
        $toUpdate = array_intersect($existingKeys, $targetKeys);

        if ($toDetach !== []) {
            $this->detach($toDetach);
        }

        $timestamp = $this->now();

        foreach ($toAttach as $id) {
            $this->attach((string) $id, $pairs[$id] ?? []);
        }

        foreach ($toUpdate as $id) {
            $this->updateExistingPivot(
                (string) $id,
                $pairs[$id] ?? [],
                $timestamp,
            );
        }

        $this->parent->forgetRelationCache($this->relationName());
    }

    /**
     * Toggle the presence of the given IDs in the pivot table.
     *
     * @param array<int, mixed>|int|string $ids
     */
    public function toggle(array|int|string $ids): void
    {
        $pairs = $this->normalizeIds($ids, []);
        $parentId = $this->parent->getAttribute($this->parentKey);

        if ($parentId === null) {
            throw new \InvalidArgumentException(
                'Cannot toggle without a parent key value.',
            );
        }

        foreach ($pairs as $id => $attrs) {
            $exists = $this->parent
                ->getConnection()
                ->table($this->table)
                ->where($this->foreignPivotKey, $parentId)
                ->where($this->relatedPivotKey, $id)
                ->exists();

            if ($exists) {
                $this->detach([$id]);
            } else {
                $this->attach([$id => $attrs]);
            }
        }

        $this->parent->forgetRelationCache($this->relationName());
    }

    /**
     * @return array<int, Model>
     */
    public function getResults(): array
    {
        $builder = $this->related->newQuery();
        $this->addPivotSelects($builder);
        $builder
            ->join(
                $this->table,
                $this->table . '.' . $this->relatedPivotKey,
                '=',
                $this->related->getTable() . '.' . $this->relatedKey,
            )
            ->where(
                $this->table . '.' . $this->foreignPivotKey,
                $this->parent->getAttribute($this->parentKey),
            );

        $rows = $builder->get();

        $results = [];
        foreach ($rows as $row) {
            [$instance, $pivot] = $this->hydrateWithPivot($row);
            if ($pivot !== []) {
                $instance->setRelation('pivot', $pivot);
            }
            $results[] = $instance;
        }

        return $results;
    }

    public function withPivot(string ...$columns): self
    {
        $this->pivotColumns = array_values(
            array_unique(array_merge($this->pivotColumns, $columns)),
        );
        return $this;
    }

    /**
     * @param array<int, Model> $parents
     * @param string $relation
     * @return array<string, mixed>
     */
    public function eagerLoad(
        array $parents,
        string $relation,
        ?callable $constraints = null,
    ): array {
        $keys = array_filter(
            array_map(
                fn (Model $parent): mixed => $parent->getAttribute(
                    $this->parentKey,
                ),
                $parents,
            ),
            static fn ($value): bool => $value !== null,
        );

        if ($keys === []) {
            return [];
        }

        $builder = $this->related->newQuery();
        $this->addPivotSelects($builder);
        $builder
            ->join(
                $this->table,
                $this->table . '.' . $this->relatedPivotKey,
                '=',
                $this->related->getTable() . '.' . $this->relatedKey,
            )
            ->whereIn($this->table . '.' . $this->foreignPivotKey, $keys);

        if ($constraints !== null) {
            $constraints($builder);
        }

        $rows = $builder->get();

        $grouped = [];
        foreach ($rows as $row) {
            [$instance, $pivot] = $this->hydrateWithPivot($row);
            if ($pivot !== []) {
                $instance->setRelation('pivot', $pivot);
            }
            $pivotKey =
                $row['pivot_' . $this->foreignPivotKey] ??
                ($row[$this->foreignPivotKey] ?? null);
            if ($pivotKey !== null) {
                $grouped[$pivotKey][] = $instance;
            }
        }

        return $grouped;
    }

    /**
     * @param array<int|string, mixed>|int|string $ids
     * @param array<string, mixed> $attributes
     * @return array<string, array<string, mixed>>
     */
    private function normalizeIds(
        array|int|string $ids,
        array $attributes,
    ): array {
        if (!is_array($ids)) {
            return [(string) $ids => $attributes];
        }

        $normalized = [];
        foreach ($ids as $key => $value) {
            if (is_array($value)) {
                $normalized[(string) $key] = $value;
            } else {
                $normalized[(string) $value] = $attributes;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function pivotPayload(
        string $relatedId,
        array $attributes,
        string $timestamp,
    ): array {
        $payload = array_merge(
            [
                $this->foreignPivotKey => $this->parent->getAttribute(
                    $this->parentKey,
                ),
                $this->relatedPivotKey => $relatedId,
            ],
            $attributes,
        );

        if (in_array('created_at', $this->pivotColumns, true)) {
            $payload['created_at'] ??= $timestamp;
        }

        if (in_array('updated_at', $this->pivotColumns, true)) {
            $payload['updated_at'] ??= $timestamp;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function updateExistingPivot(
        string $relatedId,
        array $attributes,
        string $timestamp,
    ): void {
        if (
            in_array('updated_at', $this->pivotColumns, true) &&
            !array_key_exists('updated_at', $attributes)
        ) {
            $attributes['updated_at'] = $timestamp;
        }

        if ($attributes === []) {
            return;
        }

        $this->parent
            ->getConnection()
            ->table($this->table)
            ->where(
                $this->foreignPivotKey,
                $this->parent->getAttribute($this->parentKey),
            )
            ->where($this->relatedPivotKey, $relatedId)
            ->update($attributes);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{0: Model, 1: array<string, mixed>}
     */
    private function hydrateWithPivot(array $row): array
    {
        $pivot = [];
        foreach (
            array_merge(
                [$this->foreignPivotKey, $this->relatedPivotKey],
                $this->pivotColumns,
            ) as $column
        ) {
            $alias = 'pivot_' . $column;
            $lookup = array_key_exists($alias, $row) ? $alias : $column;

            if (!array_key_exists($lookup, $row)) {
                continue;
            }

            $pivot[$alias] = $row[$lookup];
            unset($row[$lookup]);
        }

        $instance = $this->related->newFromBuilder($row);
        return [$instance, $pivot];
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format(DATE_ATOM);
    }

    private function addPivotSelects(
        \Lalaz\Database\Query\QueryBuilder $builder,
    ): void {
        // Select related columns explicitly and alias pivot columns to avoid name collisions.
        $builder->select($this->related->getTable() . '.*');

        $pivotColumns = array_unique(
            array_merge(
                [$this->foreignPivotKey, $this->relatedPivotKey],
                $this->pivotColumns,
            ),
        );

        foreach ($pivotColumns as $column) {
            $builder->selectRaw(
                sprintf('%s.%s as pivot_%s', $this->table, $column, $column),
            );
        }
    }
}
