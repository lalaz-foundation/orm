<?php

declare(strict_types=1);

namespace Lalaz\Orm\Relations;

use Lalaz\Orm\Model;

final class BelongsTo extends Relation
{
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }

    public function __construct(
        Model $related,
        private string $foreignKey,
        private string $ownerKey,
        private Model $child,
    ) {
        parent::__construct($related);
    }

    public function getResults(): mixed
    {
        $value = $this->attributeValue($this->child, $this->foreignKey);
        if ($value === null) {
            return null;
        }

        return $this->related->query()->where($this->ownerKey, $value)->first();
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
                fn (Model $parent): mixed => $this->attributeValue(
                    $parent,
                    $this->foreignKey,
                ),
                $parents,
            ),
            static fn ($value): bool => $value !== null,
        );

        if ($keys === []) {
            return [];
        }

        $query = $this->related->query()->whereIn($this->ownerKey, $keys);
        if ($constraints !== null) {
            $constraints($query);
        }

        $rows = $query->get();

        $mapped = [];
        foreach ($rows as $model) {
            $mapped[$this->attributeValue($model, $this->ownerKey)] = $model;
        }

        return $mapped;
    }

    private function attributeValue(Model $model, string $key): mixed
    {
        $value = $model->getAttribute($key);
        if ($value !== null) {
            return $value;
        }

        $camel = lcfirst(str_replace('_', '', ucwords($key, '_')));
        return $model->getAttribute($camel);
    }
}
