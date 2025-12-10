<?php

declare(strict_types=1);

namespace Lalaz\Orm\Relations;

use Lalaz\Orm\Model;

final class HasMany extends Relation
{
    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function __construct(
        Model $related,
        private string $foreignKey,
        private string $localKey,
        private Model $parent,
    ) {
        parent::__construct($related);
    }

    public function getResults(): mixed
    {
        return $this->related
            ->query()
            ->where(
                $this->foreignKey,
                $this->attributeValue($this->parent, $this->localKey),
            )
            ->get();
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
                    $this->localKey,
                ),
                $parents,
            ),
            static fn ($value): bool => $value !== null,
        );

        if ($keys === []) {
            return [];
        }

        $query = $this->related->query()->whereIn($this->foreignKey, $keys);
        if ($constraints !== null) {
            $constraints($query);
        }
        $rows = $query->get();

        $grouped = [];
        foreach ($rows as $model) {
            $key = $this->attributeValue($model, $this->foreignKey);
            $grouped[$key][] = $model;
        }

        return $grouped;
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
