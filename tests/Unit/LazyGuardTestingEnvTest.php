<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;

class TestingEnvUser extends Model
{
    protected ?string $table = "users";

    public function posts(): mixed
    {
        return $this->hasMany(TestingEnvPost::class, "user_id");
    }
}

class TestingEnvPost extends Model
{
    protected ?string $table = "posts";
}

class LazyGuardTestingEnvTest extends TestCase
{
    private ModelManager $manager;
    private string|false $previousEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousEnv = getenv("APP_ENV");
        putenv("APP_ENV=testing");

        $this->manager = orm_manager([
            "lazy_loading" => [
                "prevent" => true,
                "allow_testing" => true,
            ],
        ]);

        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, created_at TEXT, updated_at TEXT)",
        );
        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT, created_at TEXT, updated_at TEXT)",
        );

        $now = date(DATE_ATOM);
        $pdo->exec(
            "INSERT INTO users (name, created_at, updated_at) VALUES ('Ada', '$now', '$now')",
        );
        $userId = (int) $pdo->lastInsertId();
        $pdo->exec(
            "INSERT INTO posts (user_id, title, created_at, updated_at) VALUES ($userId, 'First', '$now', '$now')",
        );
    }

    protected function tearDown(): void
    {
        if ($this->previousEnv === false) {
            putenv("APP_ENV");
        } else {
            putenv("APP_ENV={$this->previousEnv}");
        }
        parent::tearDown();
    }

    public function test_lazy_loading_is_allowed_in_testing_env_when_allow_testing_is_true(): void
    {
        $user = TestingEnvUser::queryWith($this->manager)->first();
        $posts = $user->posts;
        $this->assertCount(1, $posts);
    }
}
