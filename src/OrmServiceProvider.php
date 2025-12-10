<?php

declare(strict_types=1);

namespace Lalaz\Orm;

use Lalaz\Config\Config;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Container\ServiceProvider;
use Lalaz\Database\Contracts\ConnectionInterface;
use Lalaz\Database\Contracts\ConnectionManagerInterface;
use Lalaz\Orm\Contracts\ModelValidatorInterface;
use Lalaz\Orm\Validation\NullModelValidator;

/**
 * Service provider for the ORM package.
 */
final class OrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(ModelManager::class, function (
            ContainerInterface $container,
        ): ModelManager {
            /** @var ConnectionInterface $connection */
            $connection = $container->resolve(ConnectionInterface::class);
            /** @var ConnectionManagerInterface $manager */
            $manager = $container->resolve(ConnectionManagerInterface::class);

            $config = Config::getArray('orm', []);

            $validator = $this->resolveValidator($container);

            return new ModelManager(
                $connection,
                $manager,
                $config ?? [],
                $validator,
            );
        });
    }

    private function resolveValidator(
        ContainerInterface $container,
    ): ModelValidatorInterface {
        if ($container->bound(ModelValidatorInterface::class)) {
            /** @var ModelValidatorInterface $validator */
            $validator = $container->resolve(ModelValidatorInterface::class);
            return $validator;
        }

        return new NullModelValidator();
    }
}
