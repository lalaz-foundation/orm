<?php

declare(strict_types=1);

namespace Lalaz\Orm\Exceptions;

use RuntimeException;

/**
 * Thrown when attempting to set an attribute that is not fillable.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hi@lalaz.dev>
 */
final class MassAssignmentException extends RuntimeException
{
    public static function becauseNotFillable(
        string $model,
        string $attribute,
        ?string $table = null,
        array $fillable = [],
        array $guarded = [],
    ): self {
        $fillableList = $fillable === [] ? 'none' : implode(', ', $fillable);
        $guardedList = $guarded === [] ? 'none' : implode(', ', $guarded);
        $tableInfo = $table !== null ? " (table {$table})" : '';

        $message =
            "Attribute [{$attribute}] is not fillable on model [{$model}]" .
            "{$tableInfo}. Fillable: {$fillableList}. Guarded: {$guardedList}.";

        return new self($message);
    }
}
