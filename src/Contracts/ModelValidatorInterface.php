<?php

declare(strict_types=1);

namespace Lalaz\Orm\Contracts;

use Lalaz\Orm\Model;

/**
 * Optional adapter that allows models to validate attributes before persistence.
 * Implementations live in a separate validation package and are injected into
 * the ORM at runtime when available.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hello@lalaz.dev>
 */
interface ModelValidatorInterface
{
    /**
     * Validate the model's attributes for the given lifecycle operation.
     *
     * Implementations should throw a ValidationException (or a domain-specific
     * exception) when validation fails.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     */
    public function validate(
        Model $model,
        array $data,
        array $rules,
        string $operation,
    ): void;
}
