<?php

declare(strict_types=1);

namespace Lalaz\Orm\Exceptions;

use RuntimeException;

/**
 * Thrown when a relation method does not return a Relation instance.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hello@lalaz.dev>
 */
final class InvalidRelationException extends RuntimeException
{
    public static function forRelation(
        string $relation,
        string $model,
    ): self {
        return new self(
            "Relation [{$relation}] on model [{$model}] must return a Relation instance.",
        );
    }
}
