<?php

declare(strict_types=1);

namespace Lalaz\Orm;

use Lalaz\Database\Contracts\ConnectionInterface;
use Lalaz\Database\Contracts\ConnectionManagerInterface;
use Lalaz\Orm\Contracts\ModelValidatorInterface;
use Lalaz\Orm\Validation\NullModelValidator;

/**
 * Centralizes ORM configuration and provides access to the default connection.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hi@lalaz.dev>
 */
final class ModelManager
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private ConnectionInterface $connection,
        private ConnectionManagerInterface $manager,
        private array $config = [],
        private ?ModelValidatorInterface $validator = null,
        private ?\Lalaz\Orm\Events\EventDispatcher $dispatcher = null,
    ) {
        $this->validator ??= new NullModelValidator();
    }

    public function transaction(callable $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    public function dispatcher(): \Lalaz\Orm\Events\EventDispatcher
    {
        return $this->dispatcher ??= new \Lalaz\Orm\Events\EventDispatcher();
    }

    public function connection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function manager(): ConnectionManagerInterface
    {
        return $this->manager;
    }

    public function validator(): ModelValidatorInterface
    {
        return $this->validator;
    }
}
