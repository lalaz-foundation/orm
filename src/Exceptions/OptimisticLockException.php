<?php

declare(strict_types=1);

namespace Lalaz\Orm\Exceptions;

use RuntimeException;

/**
 * Thrown when an optimistic lock check fails (stale data).
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hello@lalaz.dev>
 */
final class OptimisticLockException extends RuntimeException
{
    public static function forModel(string $model, mixed $id): self
    {
        return new self(
            "Optimistic lock failed for model [{$model}] id [{$id}].",
        );
    }
}
