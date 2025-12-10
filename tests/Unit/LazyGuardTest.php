<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Exceptions\LazyLoadingViolationException;

class GuardedUser extends Model
{
    protected ?string $table = "users";
    protected array $fillable = ["name"];

    public function posts(): mixed
    {
        return $this->hasMany(GuardedPost::class, "user_id");
    }
}

class GuardedPost extends Model
{
    protected ?string $table = "posts";
    protected array $fillable = ["user_id", "title"];
}

class LazyGuardTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager([
            "lazy_loading" => [
                "prevent" => true,
                "allow_testing" => false,
                "allowed_relations" => ["posts"],
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

    public function test_lazy_loading_allowed_list_bypasses_guard(): void
    {
        $user = GuardedUser::queryWith($this->manager)->first();
        $posts = $user->posts;
        $this->assertCount(1, $posts);
    }

    public function test_lazy_loading_not_allowed_throws(): void
    {
        $manager = orm_manager([
            "lazy_loading" => [
                "prevent" => true,
                "allow_testing" => false,
                "allowed_relations" => [],
            ],
        ]);

        $pdo = $manager->connection()->getPdo();
        $now = date(DATE_ATOM);
        $pdo->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, created_at TEXT, updated_at TEXT)",
        );
        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT, created_at TEXT, updated_at TEXT)",
        );
        $pdo->exec(
            "INSERT INTO users (name, created_at, updated_at) VALUES ('Bob', '$now', '$now')",
        );

        $user = GuardedUser::queryWith($manager)->first();

        $this->expectException(LazyLoadingViolationException::class);
        $user->posts;
    }
}
