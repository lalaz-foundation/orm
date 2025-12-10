<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Exceptions\LazyLoadingViolationException;

class RelationsUser extends Model
{
    protected ?string $table = "users";
    protected array $fillable = ["name"];

    public function posts(): mixed
    {
        return $this->hasMany(RelationsPost::class, "user_id");
    }

    public function roles(): mixed
    {
        return $this->belongsToMany(RelationsRole::class);
    }
}

class RelationsPost extends Model
{
    protected ?string $table = "posts";
    protected array $fillable = ["user_id", "title"];

    public function author(): mixed
    {
        return $this->belongsTo(RelationsUser::class, "user_id");
    }
}

class RelationsRole extends Model
{
    protected ?string $table = "roles";
    protected array $fillable = ["name"];
}

class LazyGuardUser extends Model
{
    protected ?string $table = "users";

    public function posts(): mixed
    {
        return $this->hasMany(RelationsPost::class, "user_id");
    }
}

class RelationsTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();

        $pdo->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, created_at TEXT, updated_at TEXT)",
        );
        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT, created_at TEXT, updated_at TEXT)",
        );
        $pdo->exec(
            "CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, created_at TEXT, updated_at TEXT)",
        );
        $pdo->exec(
            "CREATE TABLE roles_users (id INTEGER PRIMARY KEY AUTOINCREMENT, users_id INTEGER, roles_id INTEGER, created_at TEXT, updated_at TEXT)",
        );
    }

    public function test_has_many_and_belongs_to_return_model_instances(): void
    {
        $pdo = $this->manager->connection()->getPdo();
        $now = date(DATE_ATOM);
        $pdo->exec(
            "INSERT INTO users (name, created_at, updated_at) VALUES ('Ada', '$now', '$now')",
        );
        $userId = (int) $pdo->lastInsertId();
        $pdo->exec(
            "INSERT INTO posts (user_id, title, created_at, updated_at) VALUES ($userId, 'First', '$now', '$now')",
        );
        $pdo->exec(
            "INSERT INTO posts (user_id, title, created_at, updated_at) VALUES ($userId, 'Second', '$now', '$now')",
        );

        $user = RelationsUser::queryWith($this->manager)->first();
        $posts = $user->posts;

        $this->assertCount(2, $posts);
        $this->assertInstanceOf(RelationsPost::class, $posts[0]);

        $author = $posts[0]->author;
        $this->assertInstanceOf(RelationsUser::class, $author);
        $this->assertSame($userId, $author->getAttribute("id"));
    }

    public function test_belongs_to_many_loads_related_models(): void
    {
        $pdo = $this->manager->connection()->getPdo();
        $now = date(DATE_ATOM);
        $pdo->exec(
            "INSERT INTO users (name, created_at, updated_at) VALUES ('Ada', '$now', '$now')",
        );
        $userId = (int) $pdo->lastInsertId();

        $pdo->exec(
            "INSERT INTO roles (name, created_at, updated_at) VALUES ('admin', '$now', '$now')",
        );
        $pdo->exec(
            "INSERT INTO roles (name, created_at, updated_at) VALUES ('editor', '$now', '$now')",
        );

        $pdo->exec(
            "INSERT INTO roles_users (users_id, roles_id) VALUES ($userId, 1)",
        );
        $pdo->exec(
            "INSERT INTO roles_users (users_id, roles_id) VALUES ($userId, 2)",
        );

        $user = RelationsUser::queryWith($this->manager)->first();
        $roles = $user->roles;

        $this->assertCount(2, $roles);
        $this->assertInstanceOf(RelationsRole::class, $roles[0]);
    }

    public function test_eager_loading_populates_relations_cache(): void
    {
        $pdo = $this->manager->connection()->getPdo();
        $now = date(DATE_ATOM);
        $pdo->exec(
            "INSERT INTO users (name, created_at, updated_at) VALUES ('Ada', '$now', '$now')",
        );
        $userId = (int) $pdo->lastInsertId();
        $pdo->exec(
            "INSERT INTO posts (user_id, title, created_at, updated_at) VALUES ($userId, 'First', '$now', '$now')",
        );

        $users = (new RelationsUser($this->manager))->query()->with("posts")->get();

        $this->assertCount(1, $users);
        $relations = $users[0]->getRelations();
        $this->assertArrayHasKey("posts", $relations);
        $this->assertInstanceOf(RelationsPost::class, $relations["posts"][0]);
    }

    public function test_eager_loading_batches_relations_with_constraints(): void
    {
        $pdo = $this->manager->connection()->getPdo();
        $now = date(DATE_ATOM);
        $pdo->exec(
            "INSERT INTO users (name, created_at, updated_at) VALUES ('Ada', '$now', '$now')",
        );
        $userId = (int) $pdo->lastInsertId();
        $pdo->exec(
            "INSERT INTO posts (user_id, title, created_at, updated_at) VALUES ($userId, 'First', '$now', '$now')",
        );
        $pdo->exec(
            "INSERT INTO posts (user_id, title, created_at, updated_at) VALUES ($userId, 'Second', '$now', '$now')",
        );

        $users = (new RelationsUser($this->manager))
            ->query()
            ->with([
                "posts" => function ($builder): void {
                    $builder->where("title", "First");
                },
            ])
            ->get();

        $this->assertCount(1, $users);
        $posts = $users[0]->getRelations()["posts"] ?? [];
        $this->assertCount(1, $posts);
        $this->assertSame("First", $posts[0]->getAttribute("title"));
    }

    public function test_lock_helpers_delegate_to_builder(): void
    {
        $pdo = $this->manager->connection()->getPdo();
        $now = date(DATE_ATOM);
        $pdo->exec(
            "INSERT INTO users (name, created_at, updated_at) VALUES ('Ada', '$now', '$now')",
        );

        $query = (new RelationsUser($this->manager))->query()->lockForUpdate();

        $this->assertSame("for update", $query->builder()->lockValue());
    }

    public function test_with_pivot_stores_pivot_data_on_related_models(): void
    {
        $pdo = $this->manager->connection()->getPdo();
        $now = date(DATE_ATOM);
        $pdo->exec(
            "INSERT INTO users (name, created_at, updated_at) VALUES ('Ada', '$now', '$now')",
        );
        $userId = (int) $pdo->lastInsertId();

        $pdo->exec(
            "INSERT INTO roles (name, created_at, updated_at) VALUES ('admin', '$now', '$now')",
        );
        $roleId = (int) $pdo->lastInsertId();

        $pdo->exec(
            "INSERT INTO roles_users (users_id, roles_id, created_at) VALUES ($userId, $roleId, '$now')",
        );

        $users = (new RelationsUser($this->manager))->query()->with("roles")->get();

        $roles = $users[0]->roles;
        $this->assertArrayHasKey("pivot", $roles[0]->getRelations());
        $this->assertSame($now, $roles[0]->getRelations()["pivot"]["pivot_created_at"]);
    }

    public function test_lazy_loading_guard_throws_when_enabled(): void
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
            "INSERT INTO users (name, created_at, updated_at) VALUES ('Ada', '$now', '$now')",
        );
        $user = LazyGuardUser::queryWith($manager)->first();

        $this->expectException(LazyLoadingViolationException::class);
        $user->posts;
    }

    public function test_belongs_to_many_supports_attach_detach_sync_toggle(): void
    {
        $pdo = $this->manager->connection()->getPdo();
        $now = date(DATE_ATOM);

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS roles_users (users_id INTEGER, roles_id INTEGER, created_at TEXT, updated_at TEXT)",
        );

        $user = RelationsUser::create($this->manager, ["name" => "Cache"]);
        $userId = (int) $user->getAttribute("id");

        $role = RelationsRole::create($this->manager, ["name" => "viewer"]);
        $roleId = (int) $role->getAttribute("id");

        $user->roles()->attach($roleId, ["created_at" => $now]);
        $roles = $user->roles;
        $this->assertCount(1, $roles);

        $user->roles()->detach($roleId);
        $user->forgetRelationCache("roles");
        $this->assertCount(0, $user->roles);

        $user->roles()->sync([$roleId => ["created_at" => $now]]);
        $user->forgetRelationCache("roles");
        $this->assertCount(1, $user->roles);

        $user->roles()->toggle([$roleId]);
        $user->forgetRelationCache("roles");
        $this->assertCount(0, $user->roles);
    }

    public function test_belongs_to_many_eager_loading_aliases_pivot_columns(): void
    {
        $pdo = $this->manager->connection()->getPdo();
        $now = date(DATE_ATOM);

        $pdo->exec("DELETE FROM users");
        $pdo->exec("DELETE FROM roles");
        $pdo->exec("DELETE FROM roles_users");

        $pdo->exec(
            "INSERT INTO users (name, created_at, updated_at) VALUES ('Ada', '$now', '$now')",
        );
        $userId = (int) $pdo->lastInsertId();

        $pdo->exec(
            "INSERT INTO roles (name, created_at, updated_at) VALUES ('admin', '$now', '$now')",
        );
        $roleId = (int) $pdo->lastInsertId();

        $pdo->exec(
            "INSERT INTO roles_users (users_id, roles_id, created_at, updated_at) VALUES ($userId, $roleId, '$now', '$now')",
        );

        $user = RelationsUser::queryWith($this->manager)
            ->with("roles")
            ->first();

        $this->assertSame($roleId, $user?->roles[0]->getAttribute("id"));
        $this->assertSame(
            $userId,
            $user?->roles[0]->getRelations()["pivot"]["pivot_users_id"] ?? null,
        );
    }

    public function test_to_array_serializes_loaded_relations_respecting_visibility(): void
    {
        $pdo = $this->manager->connection()->getPdo();
        $now = date(DATE_ATOM);
        $pdo->exec(
            "INSERT INTO users (name, created_at, updated_at) VALUES ('Ada', '$now', '$now')",
        );
        $userId = (int) $pdo->lastInsertId();
        $pdo->exec(
            "INSERT INTO posts (user_id, title, created_at, updated_at) VALUES ($userId, 'First', '$now', '$now')",
        );

        $user = RelationsUser::queryWith($this->manager)
            ->with("posts")
            ->first();
        $array = $user->toArray();

        $this->assertArrayHasKey("posts", $array);
        $this->assertSame("First", $array["posts"][0]["title"] ?? null);
    }
}
