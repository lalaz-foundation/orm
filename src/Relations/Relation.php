<?php

declare(strict_types=1);

namespace Lalaz\Orm\Relations;

use Lalaz\Orm\Model;

abstract class Relation
{
    protected ?string $relationName = null;

    public function __construct(protected Model $related)
    {
    }

    abstract public function getResults(): mixed;

    public function as(string $name): static
    {
        $this->relationName = $name;
        return $this;
    }

    protected function relationName(): ?string
    {
        return $this->relationName;
    }

    public function query(): \Lalaz\Orm\Query\ModelQuery
    {
        return $this->related->query();
    }

    /**
     * Eager-load a set of parent models in bulk.
     *
     * @param array<int, Model> $parents
     * @param string $relation
     * @return array<string, mixed> keyed by parent key
     */
    public function eagerLoad(
        array $parents,
        string $relation,
        ?callable $constraints = null,
    ): array {
        // Default: fall back to per-model lazy loading.
        $results = [];
        foreach ($parents as $parent) {
            $results[$parent->getKey()] = $parent->{$relation}();
        }
        return $results;
    }
}
