<?php

declare(strict_types=1);

namespace Lalaz\Orm\Validation;

use Lalaz\Orm\Contracts\ModelValidatorInterface;
use Lalaz\Orm\Model;

/**
 * No-op validator used when the validation package is not installed.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hi@lalaz.dev>
 */
final class NullModelValidator implements ModelValidatorInterface
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     */
    public function validate(
        Model $model,
        array $data,
        array $rules,
        string $operation,
    ): void {
        // Intentionally blank – validation is optional.
    }
}
