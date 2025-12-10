<?php

declare(strict_types=1);

namespace Lalaz\Orm\Traits;

use Lalaz\Orm\Exceptions\InvalidRelationException;
use Lalaz\Orm\Exceptions\RelationNotFoundException;
use Lalaz\Orm\Relations\BelongsTo;
use Lalaz\Orm\Relations\BelongsToMany;
use Lalaz\Orm\Relations\HasMany;
use Lalaz\Orm\Relations\HasOne;
use Lalaz\Orm\Relations\Relation;

trait HasRelationships
{
    /**
     * @var array<string, mixed>
     */
    protected array $relations = [];

    protected function hasOne(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null,
    ): HasOne {
        $foreignKey ??= $this->tableName() . '_' . $this->getKeyName();
        $localKey ??= $this->getKeyName();

        /** @var \Lalaz\Orm\Model $instance */
        $instance = new $related($this->manager);
        return new HasOne($instance, $foreignKey, $localKey, $this);
    }

    protected function hasMany(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null,
    ): HasMany {
        $foreignKey ??= $this->tableName() . '_' . $this->getKeyName();
        $localKey ??= $this->getKeyName();

        /** @var \Lalaz\Orm\Model $instance */
        $instance = new $related($this->manager);
        return new HasMany($instance, $foreignKey, $localKey, $this);
    }

    protected function belongsTo(
        string $related,
        ?string $foreignKey = null,
        ?string $ownerKey = null,
    ): BelongsTo {
        $foreignKey ??=
            $related === static::class
                ? $this->getKeyName()
                : strtolower(basename(str_replace('\\', '/', $related))) .
                    '_id';
        $ownerKey ??= 'id';

        /** @var \Lalaz\Orm\Model $instance */
        $instance = new $related($this->manager);
        $ownerKey ??= $instance->getKeyName();
        return new BelongsTo($instance, $foreignKey, $ownerKey, $this);
    }

    protected function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
    ): BelongsToMany {
        /** @var \Lalaz\Orm\Model $instance */
        $instance = new $related($this->manager);

        $table ??= $this->joinNames($this->tableName(), $instance->tableName());
        $foreignPivotKey ??= $this->tableName() . '_' . $this->getKeyName();
        $relatedPivotKey ??=
            $instance->tableName() . '_' . $instance->getKeyName();
        $parentKey ??= $this->getKeyName();
        $relatedKey ??= $instance->getKeyName();

        return new BelongsToMany(
            $instance,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $this,
        );
    }

    /**
     * @param string $name
     * @return mixed
     */
    protected function getRelationValue(string $name): mixed
    {
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        if (!method_exists($this, $name)) {
            throw RelationNotFoundException::forRelation(
                $name,
                static::class,
                method_exists($this, 'tableName') ? $this->tableName() : null,
            );
        }

        $relation = $this->{$name}();
        if (!$relation instanceof Relation) {
            throw InvalidRelationException::forRelation($name, static::class);
        }

        $relation->as($name);
        $this->relations[$name] = $relation->getResults();
        return $this->relations[$name];
    }

    public function setRelation(string $name, mixed $value): void
    {
        $this->relations[$name] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    public function forgetRelationCache(?string $name = null): void
    {
        if ($name === null) {
            $this->relations = [];
            return;
        }

        unset($this->relations[$name]);
    }

    private function joinNames(string ...$segments): string
    {
        sort($segments);
        return implode('_', $segments);
    }
}
