<?php declare(strict_types=1);

use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;
use Lalaz\Orm\Contracts\ModelValidatorInterface;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Validation\NullModelValidator;

/**
 * Build a ModelManager backed by an in-memory SQLite connection.
 *
 * @param array<string, mixed> $ormConfigOverrides
 * @param array<string, mixed> $databaseOverrides
 */
function orm_manager(
    array $ormConfigOverrides = [],
    array $databaseOverrides = [],
    ?ModelValidatorInterface $validator = null,
): ModelManager {
    $databaseConfig = array_replace_recursive(
        [
            "driver" => "sqlite",
            "connections" => [
                "sqlite" => [
                    "path" => ":memory:",
                    "options" => [],
                ],
            ],
        ],
        $databaseOverrides,
    );

    $manager = new ConnectionManager($databaseConfig);
    $connection = new Connection($manager);

    $ormConfig = array_replace_recursive(
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
            "mass_assignment" => [
                "throw_on_violation" => true,
            ],
            "dates" => [
                "timezone" => null,
                "format" => DATE_ATOM,
            ],
            "naming" => [
                "hydrate" => null,
            ],
            "lazy_loading" => [
                "prevent" => false,
                "allow_testing" => true,
                "allowed_relations" => [],
            ],
            "validation" => [
                "enabled" => true,
            ],
        ],
        $ormConfigOverrides,
    );

    return new ModelManager(
        $connection,
        $manager,
        $ormConfig,
        $validator ?? new NullModelValidator(),
    );
}
