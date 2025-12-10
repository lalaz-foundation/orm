<?php declare(strict_types=1);

use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;
use Lalaz\Orm\Contracts\ModelValidatorInterface;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Tests\Integration\Support\IntegrationEnvironment;
use Lalaz\Orm\Validation\NullModelValidator;

require_once __DIR__ . "/Support/IntegrationEnvironment.php";

/**
 * Build a ModelManager backed by the integration database.
 */
function orm_integration_manager(string $driver): ModelManager
{
    $environment = IntegrationEnvironment::instance();

    $config = match ($driver) {
        "mysql" => $environment->mysqlConfig(),
        "postgres" => $environment->postgresConfig(),
        default => throw new InvalidArgumentException(
            sprintf("Unsupported driver [%s].", $driver),
        ),
    };

    $manager = new ConnectionManager($config);
    $connection = new Connection($manager);

    return new ModelManager(
        $connection,
        $manager,
        [
            "timestamps" => [
                "enabled" => true,
                "created_at" => "created_at",
                "updated_at" => "updated_at",
            ],
            "soft_deletes" => [
                "enabled" => false,
                "deleted_at" => "deleted_at",
            ],
            "enforce_fillable" => true,
            "lazy_loading" => ["prevent" => false],
            "validation" => ["enabled" => true],
            "naming" => ["hydrate" => null],
        ],
        new NullModelValidator(),
    );
}
