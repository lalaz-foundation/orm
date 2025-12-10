<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Integration\Support;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Boots MySQL/Postgres containers for integration tests when Docker/Podman
 * is available. Tests are skipped automatically if the environment cannot
 * be started (no compose binary, ports unavailable, etc.).
 */
final class IntegrationEnvironment
{
    private const MYSQL_PORT = 33306;
    private const POSTGRES_PORT = 35432;

    private static ?self $instance = null;

    private bool $booted = false;
    private ?string $composeBinary = null;
    private ?string $skipReason = null;

    private function __construct() {}

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        if ($this->booted || $this->skipReason !== null) {
            return;
        }

        try {
            $this->composeBinary ??= $this->detectComposeBinary();
            $this->runCompose("up -d");
            $this->waitForMysql();
            $this->waitForPostgres();
            $this->booted = true;

            register_shutdown_function(fn() => $this->shutdown());
        } catch (RuntimeException $exception) {
            $this->skipReason = $exception->getMessage();
        }
    }

    public function shutdown(): void
    {
        if (!$this->booted || $this->composeBinary === null) {
            return;
        }

        $this->runCompose("down -v");
        $this->booted = false;
    }

    /**
     * @return array<string, mixed>
     */
    public function mysqlConfig(): array
    {
        return [
            "driver" => "mysql",
            "connections" => [
                "mysql" => [
                    "host" => "127.0.0.1",
                    "port" => self::MYSQL_PORT,
                    "database" => "lalaz_test",
                    "username" => "root",
                    "password" => "secret",
                    "charset" => "utf8mb4",
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function postgresConfig(): array
    {
        return [
            "driver" => "postgres",
            "connections" => [
                "postgres" => [
                    "host" => "127.0.0.1",
                    "port" => self::POSTGRES_PORT,
                    "database" => "lalaz_test",
                    "username" => "postgres",
                    "password" => "secret",
                ],
            ],
        ];
    }

    private function detectComposeBinary(): string
    {
        if ($this->commandExists("docker")) {
            return "docker compose";
        }

        if ($this->commandExists("docker-compose")) {
            return "docker-compose";
        }

        if ($this->commandExists("podman")) {
            return "podman compose";
        }

        if ($this->commandExists("podman-compose")) {
            return "podman-compose";
        }

        throw new RuntimeException(
            "Neither Docker nor Podman is available to run integration tests.",
        );
    }

    private function commandExists(string $command): bool
    {
        $result = trim(
            (string) shell_exec(
                sprintf("command -v %s", escapeshellarg($command)),
            ),
        );
        return $result !== "";
    }

    private function runCompose(string $arguments): void
    {
        $composeFile = escapeshellarg($this->composeFile());
        $command = sprintf(
            "%s -f %s %s",
            $this->composeBinary,
            $composeFile,
            $arguments,
        );

        exec($command, $output, $status);

        if ($status !== 0) {
            throw new RuntimeException(
                sprintf(
                    "Docker compose command failed (%s): %s",
                    $command,
                    implode("\n", $output),
                ),
            );
        }
    }

    private function composeFile(): string
    {
        return dirname(__DIR__, 3) . "/docker-compose.integration.yml";
    }

    private function waitForMysql(): void
    {
        $dsn = sprintf(
            "mysql:host=127.0.0.1;port=%d;dbname=lalaz_test",
            self::MYSQL_PORT,
        );
        $this->waitForConnection($dsn, "root", "secret");
    }

    private function waitForPostgres(): void
    {
        $dsn = sprintf(
            "pgsql:host=127.0.0.1;port=%d;dbname=lalaz_test",
            self::POSTGRES_PORT,
        );
        $this->waitForConnection($dsn, "postgres", "secret");
    }

    private function waitForConnection(
        string $dsn,
        string $username,
        string $password,
        int $timeoutSeconds = 60,
    ): void {
        $start = time();

        do {
            try {
                new PDO($dsn, $username, $password, [
                    PDO::ATTR_TIMEOUT => 1,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                return;
            } catch (PDOException) {
                usleep(500_000);
            }
        } while (time() - $start < $timeoutSeconds);

        throw new RuntimeException(
            sprintf(
                "Unable to connect to DSN [%s] within %d seconds.",
                $dsn,
                $timeoutSeconds,
            ),
        );
    }

    public function isAvailable(): bool
    {
        return $this->skipReason === null && $this->booted;
    }

    public function skipReason(): ?string
    {
        return $this->skipReason;
    }
}
