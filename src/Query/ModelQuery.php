<?php

declare(strict_types=1);

namespace Lalaz\Orm\Query;

use Lalaz\Database\Query\QueryBuilder;
use Lalaz\Orm\Exceptions\InvalidRelationException;
use Lalaz\Orm\Exceptions\RelationNotFoundException;
use Lalaz\Orm\Model;
use Lalaz\Orm\Relations\Relation;

final class ModelQuery
{
    /**
     * @var array<int|string, array<string, mixed>>
     */
    private array $with = [];

    public function __construct(
        private Model $model,
        private QueryBuilder $builder,
        private bool $includeTrashed = false,
        private bool $applyGlobalScopes = true,
    ) {
    }

    /**
     * Eager load the given relations.
     *
     * @param array<int, string>|string $relations
     */
    public function with(array|string $relations): self
    {
        $relations = is_array($relations) ? $relations : [$relations];
        foreach ($relations as $key => $value) {
            $name = is_int($key) ? $value : $key;

            if (!method_exists($this->model, $name)) {
                throw RelationNotFoundException::forRelation(
                    (string) $name,
                    $this->model::class,
                    $this->model->getTable(),
                );
            }

            $this->with[$name] = [
                'constraints' =>
                    $value instanceof \Closure && !is_int($key)
                        ? $value
                        : null,
            ];
        }
        return $this;
    }

    public function builder(): QueryBuilder
    {
        return $this->builder;
    }

    public function withoutGlobalScopes(?array $scopes = null): self
    {
        if ($scopes === []) {
            return $this;
        }

        $builder = $this->model->newQuery(
            includeTrashed: $this->includeTrashed,
            applyGlobalScopes: false,
        );

        if (is_array($scopes)) {
            foreach ($this->model::getGlobalScopes() as $name => $scope) {
                if (in_array($name, $scopes, true)) {
                    continue;
                }
                $scope($builder, $this->model);
            }
        }

        $this->builder = $builder;
        $this->applyGlobalScopes = false;
        return $this;
    }

    /**
     * Insert many records efficiently.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertMany(array $rows): bool
    {
        return $this->builder->insertMany($rows);
    }

    /**
     * Upsert records using database native merge semantics.
     *
     * @param array<int, array<string, mixed>> $values
     * @param array<int, string>|string $uniqueBy
     * @param array<int, string>|null $updateColumns
     */
    public function upsert(
        array $values,
        array|string $uniqueBy,
        ?array $updateColumns = null,
    ): bool {
        return $this->builder->upsert($values, $uniqueBy, $updateColumns);
    }

    /**
     * Update rows matching the given conditions.
     *
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $values
     */
    public function updateWhere(array $conditions, array $values): int
    {
        $clone = clone $this->builder;
        foreach ($conditions as $column => $value) {
            $clone->where($column, $value);
        }

        return $clone->update($values);
    }

    /**
     * Delete rows matching the given conditions.
     *
     * @param array<string, mixed> $conditions
     */
    public function deleteWhere(array $conditions): int
    {
        $clone = clone $this->builder;
        foreach ($conditions as $column => $value) {
            $clone->where($column, $value);
        }

        return $clone->delete();
    }

    public function where(
        string $column,
        mixed $operator,
        mixed $value = null,
    ): self {
        if (func_num_args() === 2) {
            $this->builder->where($column, $operator);
        } else {
            $this->builder->where($column, $operator, $value);
        }

        return $this;
    }

    public function __call(string $name, array $arguments): mixed
    {
        $scope = 'scope' . ucfirst($name);
        if (method_exists($this->model, $scope)) {
            $this->model->{$scope}($this, ...$arguments);
            return $this;
        }

        if (method_exists($this->builder, $name)) {
            $result = $this->builder->{$name}(...$arguments);
            // Preserve fluent interface when builder returns itself.
            return $result === $this->builder ? $this : $result;
        }

        throw new \BadMethodCallException(
            sprintf('Method %s does not exist on %s.', $name, static::class),
        );
    }

    /**
     * @param array<int, mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        $this->builder->whereIn($column, $values);
        return $this;
    }

    /**
     * Paginate the results into a simple array payload.
     *
     * @return array{
     *     data: array<int, Model>,
     *     total: int,
     *     per_page: int,
     *     current_page: int,
     *     last_page: int,
     *     from: int|null,
     *     to: int|null
     * }
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        $countBuilder = clone $this->builder;
        $total = $countBuilder->count();

        $pagedBuilder = clone $this->builder;
        $pagedBuilder->forPage($page, $perPage);

        $query = new self($this->model, $pagedBuilder);
        $query->with = $this->with;

        $data = $query->get();
        $from = $data === [] ? null : (($page - 1) * $perPage) + 1;
        $to = $data === [] ? null : $from + count($data) - 1;

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Chunk through results, invoking the callback for each batch.
     * Returning false from the callback will stop further processing.
     *
     * @param callable(array<int, Model>, int): (bool|null) $callback
     */
    public function chunk(int $count, callable $callback): void
    {
        $count = max(1, $count);
        $page = 1;

        while (true) {
            $builder = clone $this->builder;
            $builder->forPage($page, $count);

            $query = new self($this->model, $builder);
            $query->with = $this->with;

            $results = $query->get();
            if ($results === []) {
                break;
            }

            $shouldContinue = $callback($results, $page);
            if ($shouldContinue === false) {
                break;
            }

            if (count($results) < $count) {
                break;
            }

            $page++;
        }
    }

    /**
     * Iterate each model, optionally stopping early when the callback returns false.
     *
     * @param callable(Model): (bool|null) $callback
     */
    public function each(callable $callback, int $count = 100): void
    {
        $this->chunk($count, function (array $models) use ($callback) {
            foreach ($models as $model) {
                $continue = $callback($model);
                if ($continue === false) {
                    return false;
                }
            }
            return true;
        });
    }

    public function first(): ?Model
    {
        $row = $this->builder->first();
        if (!is_array($row)) {
            return null;
        }

        $instance = $this->model->newFromBuilder($row);
        $this->eagerLoad([$instance]);

        return $instance;
    }

    /**
     * @return array<int, Model>
     */
    public function get(): array
    {
        $rows = $this->builder->get();
        $results = [];

        foreach ($rows as $row) {
            $results[] = $this->model->newFromBuilder($row);
        }

        $this->eagerLoad($results);
        return $results;
    }

    public function find(mixed $id): ?Model
    {
        $row = $this->builder->where($this->model->getKeyName(), $id)->first();

        if (!is_array($row)) {
            return null;
        }

        $instance = $this->model->newFromBuilder($row);
        $this->eagerLoad([$instance]);

        return $instance;
    }

    public function findOrFail(mixed $id): Model
    {
        $result = $this->find($id);
        if ($result === null) {
            throw \Lalaz\Orm\Exceptions\ModelNotFoundException::forModel(
                $this->model::class,
                $id,
                $this->model->getTable(),
            );
        }
        return $result;
    }

    public function firstOrFail(): Model
    {
        $result = $this->first();
        if ($result === null) {
            throw \Lalaz\Orm\Exceptions\ModelNotFoundException::forModel(
                $this->model::class,
                null,
                $this->model->getTable(),
            );
        }
        return $result;
    }

    public function lockForUpdate(): self
    {
        $this->builder->lock('for update');
        return $this;
    }

    public function sharedLock(): self
    {
        $this->builder->lock('share');
        return $this;
    }

    /**
     * @param array<int, Model> $models
     */
    private function eagerLoad(array $models): void
    {
        if ($models === [] || $this->with === []) {
            return;
        }

        foreach ($this->with as $name => $meta) {
            $constraints = $meta['constraints'] ?? null;

            if (!method_exists($this->model, $name)) {
                throw RelationNotFoundException::forRelation(
                    $name,
                    $this->model::class,
                    $this->model->getTable(),
                );
            }

            // Create relation instance from a fresh model to avoid mutated state.
            $relationInstance = $this->model->{$name}();
            if (!$relationInstance instanceof Relation) {
                throw InvalidRelationException::forRelation(
                    $name,
                    $this->model::class,
                );
            }

            $relationInstance->as($name);

            // Apply constraints for eager loading if provided.
            $results = $relationInstance->eagerLoad(
                $models,
                $name,
                $constraints,
            );

            foreach ($models as $model) {
                if (
                    $relationInstance instanceof \Lalaz\Orm\Relations\BelongsTo
                ) {
                    $foreign = $model->getAttribute(
                        $relationInstance->getForeignKey(),
                    );
                    $model->setRelation(
                        $name,
                        $foreign !== null ? $results[$foreign] ?? null : null,
                    );
                } elseif (
                    $relationInstance instanceof \Lalaz\Orm\Relations\HasOne
                ) {
                    $local = $model->getAttribute(
                        $relationInstance->getLocalKey(),
                    );
                    $model->setRelation(
                        $name,
                        $local !== null ? $results[$local] ?? null : null,
                    );
                } elseif (
                    $relationInstance instanceof \Lalaz\Orm\Relations\HasMany ||
                    $relationInstance instanceof
                        \Lalaz\Orm\Relations\BelongsToMany
                ) {
                    $localKey =
                        $relationInstance instanceof
                        \Lalaz\Orm\Relations\HasMany
                            ? $relationInstance->getLocalKey()
                            : $relationInstance->getParentKey();

                    $key =
                        $localKey !== null
                            ? $model->getAttribute($localKey)
                            : null;
                    $model->setRelation(
                        $name,
                        $key !== null ? $results[$key] ?? [] : [],
                    );
                } else {
                    $model->setRelation($name, $relationInstance->getResults());
                }
            }
        }
    }
}
