<?php

declare(strict_types=1);

namespace Lalaz\Orm\Testing;

use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;

/**
 * Lightweight factory for building and creating model instances in tests.
 */
class Factory
{
    /**
     * @var callable(): array<string, mixed>
     */
    private $definition;

    /**
     * @var array<int, callable(Model): void>
     */
    private array $states = [];

    private int $count = 1;

    /**
     * @param callable(): array<string, mixed> $definition
     */
    public function __construct(
        private ModelManager $manager,
        private string $modelClass,
        callable $definition,
    ) {
        $this->definition = $definition;
    }

    public static function new(
        ModelManager $manager,
        string $modelClass,
        callable $definition,
    ): self {
        return new self($manager, $modelClass, $definition);
    }

    public function count(int $count): self
    {
        $this->count = max(1, $count);
        return $this;
    }

    /**
     * @param callable(Model): void $state
     */
    public function state(callable $state): self
    {
        $this->states[] = $state;
        return $this;
    }

    /**
     * Build (but do not persist) models.
     *
     * @return array<int, Model>|Model
     */
    public function build(): array|Model
    {
        $results = [];
        for ($i = 0; $i < $this->count; $i++) {
            /** @var Model $model */
            $model = call_user_func($this->definition);
            $model = $this->hydrate($model);
            $results[] = $model;
        }

        return $this->count === 1 ? $results[0] : $results;
    }

    /**
     * Persist models.
     *
     * @return array<int, Model>|Model
     */
    public function create(): array|Model
    {
        $built = $this->build();

        if ($built instanceof Model) {
            $built->save();
            return $built;
        }

        foreach ($built as $model) {
            $model->save();
        }

        return $built;
    }

    private function hydrate(Model $model): Model
    {
        foreach ($this->states as $state) {
            $state($model);
        }

        return $model;
    }
}
