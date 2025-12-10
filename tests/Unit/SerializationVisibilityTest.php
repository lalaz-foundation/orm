<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;

final class VisibleUser extends Model
{
    protected ?string $table = "users";
    protected array $fillable = ["name", "secret"];
    protected array $hidden = ["secret"];

    public function posts(): mixed
    {
        return $this->hasMany(VisiblePost::class, "user_id");
    }
}

final class VisiblePost extends Model
{
    protected ?string $table = "posts";
    protected array $fillable = ["user_id", "title", "internal"];
    protected array $visible = ["title"]; // only expose title
}

class SerializationVisibilityTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, secret TEXT, created_at TEXT, updated_at TEXT)",
        );
        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT, internal TEXT, created_at TEXT, updated_at TEXT)",
        );

        $now = date(DATE_ATOM);
        $pdo->exec(
            "INSERT INTO users (name, secret, created_at, updated_at) VALUES ('Ada', 'hidden', '$now', '$now')",
        );
        $userId = (int) $pdo->lastInsertId();
        $pdo->exec(
            "INSERT INTO posts (user_id, title, internal, created_at, updated_at) VALUES ($userId, 'First', 'meta', '$now', '$now')",
        );
    }

    public function test_hidden_and_visible_are_respected_on_toArray_toJson_including_relations(): void
    {
        $user = VisibleUser::queryWith($this->manager)
            ->with("posts")
            ->first();

        $array = $user->toArray();

        $this->assertArrayHasKey("name", $array);
        $this->assertArrayNotHasKey("secret", $array);

        $this->assertArrayHasKey("title", $array["posts"][0] ?? []);
        $this->assertArrayNotHasKey("internal", $array["posts"][0] ?? []);

        $json = json_decode($user->toJson(), true);
        $this->assertArrayNotHasKey("secret", $json);
    }
}
