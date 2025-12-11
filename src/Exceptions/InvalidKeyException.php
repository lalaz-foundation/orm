<?php

declare(strict_types=1);

namespace Lalaz\Orm\Exceptions;

use RuntimeException;

/**
 * Thrown when a model key cannot be generated or is invalid.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hi@lalaz.dev>
 */
final class InvalidKeyException extends RuntimeException
{
    public static function missingKey(string $model): self
    {
        return new self("Missing primary key value for model [{$model}].");
    }
}
