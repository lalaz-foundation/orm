<?php

declare(strict_types=1);

namespace Lalaz\Orm\Exceptions;

use RuntimeException;

/**
 * Thrown when a model cannot be found for a "find or fail" style call.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hi@lalaz.dev>
 */
final class ModelNotFoundException extends RuntimeException
{
    public static function forModel(
        string $model,
        mixed $id = null,
        ?string $table = null,
    ): self {
        $tableInfo = $table !== null ? " (table {$table})" : '';
        $message =
            'Model [' .
            $model .
            "{$tableInfo}] not found" .
            ($id !== null ? " for ID [$id]" : '') .
            '.';

        return new self($message);
    }
}
