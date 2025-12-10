<?php

declare(strict_types=1);

namespace Lalaz\Orm\Traits;

/**
 * Attribute storage, casting, visibility, and dirty tracking.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hello@lalaz.dev>
 */
trait HasAttributes
{
    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * @var array<string, mixed>
     */
    protected array $original = [];

    /**
     * @var array<int, string>
     */
    protected array $hidden = [];

    /**
     * @var array<int, string> Explicitly visible attributes (overrides hidden).
     */
    protected array $visible = [];

    /**
     * @var array<int, string>
     */
    protected array $fillable = [];

    /**
     * @var array<int, string> Guarded attributes that cannot be mass-assigned.
     */
    protected array $guarded = [];

    /**
     * @var array<string, string> Cast definitions (e.g., ['age' => 'int'])
     */
    protected array $casts = [];

    /**
     * @param string $key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        if (method_exists($this, $key)) {
            return $this->getRelation($key);
        }

        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        if (method_exists($this, $key)) {
            // allow relations to be set externally
            $this->{$key} = $value;
            return;
        }

        $this->setAttribute($key, $value);
    }

    /**
     * Check if an attribute or relation exists.
     *
     * Required for Twig and other template engines to access dynamic properties.
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return array_key_exists($key, $this->attributes)
            || array_key_exists($key, $this->relations)
            || method_exists($this, $key);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param bool $force
     */
    public function fill(array $attributes, bool $force = false): void
    {
        foreach ($attributes as $key => $value) {
            if ($this->enforceFillable && !$force && !$this->isFillable($key)) {
                if ($this->throwOnMassAssignment) {
                    throw \Lalaz\Orm\Exceptions\MassAssignmentException::becauseNotFillable(
                        static::class,
                        (string) $key,
                        method_exists($this, 'tableName')
                            ? $this->tableName()
                            : null,
                        $this->fillable,
                        $this->guarded,
                    );
                }

                continue;
            }

            $this->setAttribute($key, $value);
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function forceFill(array $attributes): void
    {
        $this->fill($attributes, true);
    }

    public function getAttribute(string $key): mixed
    {
        if (!array_key_exists($key, $this->attributes)) {
            return null;
        }

        $value = $this->attributes[$key];

        $accessor = 'get' . $this->studly($key) . 'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->{$accessor}($value);
        }

        return $this->castAttribute($key, $value);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function setAttribute(
        string $key,
        mixed $value,
        bool $markDirty = true,
    ): void {
        $mutator = 'set' . $this->studly($key) . 'Attribute';
        if (method_exists($this, $mutator)) {
            $this->{$mutator}($value);
            return;
        }

        $value = $this->prepareForStorage($key, $value);
        $this->attributes[$key] = $value;

        if ($markDirty && array_key_exists($key, $this->original) === false) {
            $this->original[$key] = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [];
        foreach ($this->attributes as $key => $value) {
            if (
                $this->visible !== [] &&
                !in_array($key, $this->visible, true)
            ) {
                continue;
            }

            if ($this->visible === [] && in_array($key, $this->hidden, true)) {
                continue;
            }
            $array[$key] = $this->castAttribute($key, $value);
        }

        if (method_exists($this, 'getRelations')) {
            foreach ($this->getRelations() as $name => $relation) {
                if (
                    $this->visible !== [] &&
                    !in_array($name, $this->visible, true)
                ) {
                    continue;
                }

                if (
                    $this->visible === [] &&
                    in_array($name, $this->hidden, true)
                ) {
                    continue;
                }

                if (is_array($relation)) {
                    $array[$name] = array_map(function ($item) {
                        return $item instanceof \Lalaz\Orm\Model
                            ? $item->toArray()
                            : $item;
                    }, $relation);
                } elseif ($relation instanceof \Lalaz\Orm\Model) {
                    $array[$name] = $relation->toArray();
                } else {
                    $array[$name] = $relation;
                }
            }
        }
        return $array;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options) ?: '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            $original = $this->original[$key] ?? null;
            if (
                !array_key_exists($key, $this->original) ||
                $value !== $original
            ) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            $original = $this->original[$key] ?? null;
            $current = $this->attributes[$key] ?? null;

            // If original is empty (new model), any set attribute counts as dirty.
            if ($this->original === []) {
                return array_key_exists($key, $this->attributes);
            }

            return $current !== $original;
        }

        return $this->getDirty() !== [];
    }

    public function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        [$cast, $meta] = $this->getCast($key);
        if ($cast === null) {
            return $value;
        }

        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => is_string($value)
                ? json_decode($value, true)
                : (array) $value,
            'string' => (string) $value,
            'datetime',
            'datetime_immutable',
            'date',
            'timestamp'
                => $this->parseDate($value),
            'enum' => $this->castToEnum($meta['class'], $value),
            default => $value,
        };
    }

    protected function isFillable(string $key): bool
    {
        if (in_array('*', $this->guarded, true)) {
            return false;
        }

        if (in_array($key, $this->guarded, true)) {
            return false;
        }

        if ($this->fillable === []) {
            return true;
        }

        return in_array($key, $this->fillable, true);
    }

    private function getCast(string $key): array
    {
        if (!isset($this->casts[$key])) {
            return [null, []];
        }

        $cast = $this->casts[$key];

        if (is_string($cast) && str_starts_with($cast, 'enum:')) {
            return ['enum', ['class' => substr($cast, 5)]];
        }

        if (
            is_string($cast) &&
            class_exists($cast) &&
            is_subclass_of($cast, \UnitEnum::class)
        ) {
            return ['enum', ['class' => $cast]];
        }

        return [$cast, []];
    }

    private function prepareForStorage(string $key, mixed $value): mixed
    {
        [$cast, $meta] = $this->getCast($key);

        return match ($cast) {
            'datetime', 'datetime_immutable', 'date', 'timestamp' => $value ===
            null
                ? null
                : $this->formatDateTime($value),
            'array', 'json' => is_string($value) ? $value : json_encode($value),
            'enum' => $value instanceof \UnitEnum
                ? ($value instanceof \BackedEnum
                    ? $value->value
                    : $value->name)
                : $value,
            default => $value,
        };
    }

    private function parseDate(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value->setTimezone($this->resolveTimezone());
        }

        if ($value instanceof \DateTimeInterface) {
            return (new \DateTimeImmutable(
                $value->format(DATE_ATOM),
            ))->setTimezone($this->resolveTimezone());
        }

        $date = new \DateTimeImmutable(
            is_int($value) || ctype_digit((string) $value)
                ? '@' . $value
                : (string) $value,
            $this->resolveTimezone(),
        );

        return $date->setTimezone($this->resolveTimezone());
    }

    private function castToEnum(string $class, mixed $value): \UnitEnum
    {
        if (is_subclass_of($class, \BackedEnum::class)) {
            return $class::from($value);
        }

        foreach ($class::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        throw new \InvalidArgumentException(
            "Value [$value] is not a valid case for enum [$class].",
        );
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }
}
