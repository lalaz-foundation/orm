<?php

declare(strict_types=1);

namespace Lalaz\Orm\Exceptions;

use RuntimeException;

/**
 * Thrown when attempting to access an undefined relation.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hi@lalaz.dev>
 */
final class RelationNotFoundException extends RuntimeException
{
    public static function forRelation(
        string $relation,
        string $model,
        ?string $table = null,
        array $available = [],
    ): self {
        $tableInfo = $table !== null ? " (table {$table})" : '';
        $availableInfo =
            $available === []
                ? ''
                : ' Available: ' . implode(', ', $available) . '.';

        return new self(
            "Relation [{$relation}] was not found on model [{$model}{$tableInfo}].{$availableInfo}",
        );
    }
}
