<?php

declare(strict_types=1);

namespace Lalaz\Orm\Exceptions;

use RuntimeException;

/**
 * Thrown when lazy loading is prevented by configuration.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hi@lalaz.dev>
 */
final class LazyLoadingViolationException extends RuntimeException
{
    public static function forRelation(string $relation, string $model): self
    {
        return new self(
            "Lazy loading of relation [{$relation}] is disabled for {$model}. Use eager loading via with().",
        );
    }
}
