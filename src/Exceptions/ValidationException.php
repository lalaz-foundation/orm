<?php

declare(strict_types=1);

namespace Lalaz\Orm\Exceptions;

use RuntimeException;

/**
 * Generic validation failure wrapper for model persistence.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hello@lalaz.dev>
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(
        string $message,
        private array $errors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param array<string, array<int, string>> $errors
     */
    public static function failed(array $errors): self
    {
        return new self('Model validation failed.', $errors);
    }
}
