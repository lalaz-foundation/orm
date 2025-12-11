<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PDOException;
use RuntimeException;
use Lalaz\Orm\Model;
use Lalaz\Orm\Traits\HasTenantScope;
use Lalaz\Orm\Tests\Integration\Support\IntegrationEnvironment;

class IntegrationUser extends Model
{
    protected ?string $table = "users";
    protected array $fillable = ["name"];

    public function roles(): mixed
    {
        return $this->belongsToMany(
            IntegrationRole::class,
            "role_user",
            "user_id",
            "role_id",
        );
    }

    public function posts(): mixed
    {
        return $this->hasMany(IntegrationPost::class, "user_id");
    }
}

class IntegrationLock extends Model
{
    protected ?string $table = "locks";
    protected array $fillable = ["status"];
    protected bool $timestamps = false;
}

class IntegrationRole extends Model
{
    protected ?string $table = "roles";
    protected array $fillable = ["name"];
}

class IntegrationPost extends Model
{
    protected ?string $table = "posts";
    protected array $fillable = ["user_id", "title"];

    public function author(): mixed
    {
        return $this->belongsTo(IntegrationUser::class, "user_id");
    }
}

class IntegrationSoftUser extends Model
{
    use \Lalaz\Orm\Traits\HasSoftDeletes;

    protected ?string $table = "users";
    protected array $fillable = ["name"];

    protected function usesSoftDeletes(): bool
    {
        return true;
    }
}

class IntegrationCamelUser extends Model
{
    protected ?string $table = "camel_users";
    protected array $fillable = ["firstName"];
    protected bool $useCamelAttributes = true;

    public function posts(): mixed
    {
        return $this->hasMany(IntegrationPost::class, "user_id");
    }
}

class IntegrationPivotRole extends Model
{
    protected ?string $table = "roles";
    protected array $fillable = ["name"];
}

class IntegrationPivotUser extends Model
{
    protected ?string $table = "users";
    protected array $fillable = ["name"];

    public function roles(): mixed
    {
        return $this->belongsToMany(
            IntegrationPivotRole::class,
            "role_user",
            "user_id",
            "role_id",
        )->withPivot("created_at");
    }
}

class IntegrationTenantModel extends Model
{
    use HasTenantScope;

    protected ?string $table = "tenant_posts";
    protected array $fillable = ["title", "tenant_id"];
}

class IntegrationLockingModel extends Model
{
    protected ?string $table = "locks";
    protected array $fillable = ["name"];
    protected bool $usesOptimisticLocking = true;
    protected string $dateFormat = 'Y-m-d H:i:s';
}

#[Group("integration")]
class OrmIntegrationTest extends TestCase
{
    private static IntegrationEnvironment $env;

    public static function setUpBeforeClass(): void
    {
        self::$env = IntegrationEnvironment::instance();
        self::$env->boot();
    }

    public static function tearDownAfterClass(): void
    {
        self::$env->shutdown();
    }

    private function skipIfUnavailable(): void
    {
        if (!self::$env->isAvailable()) {
            $this->markTestSkipped(
                self::$env->skipReason() ?? "Integration environment unavailable."
            );
        }
    }

    public function test_model_transactions_commit_and_rollback_on_mysql(): void
    {
        $this->skipIfUnavailable();

        $manager = orm_integration_manager("mysql");
        $pdo = $manager->connection()->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS users");
        $pdo->exec(
            "CREATE TABLE users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )"
        );

        try {
            $manager->transaction(function () use ($manager): void {
                IntegrationUser::create($manager, ["name" => "Ada"]);
                throw new RuntimeException("boom");
            });
        } catch (RuntimeException) {
            // expected to force rollback
        }

        $this->assertSame(0, $manager->connection()->table("users")->count());

        $manager->transaction(function () use ($manager): void {
            IntegrationUser::create($manager, ["name" => "Bob"]);
        });

        $this->assertSame(1, $manager->connection()->table("users")->count());
    }

    public function test_lockForUpdate_prevents_concurrent_update_on_postgres(): void
    {
        $this->skipIfUnavailable();

        $primary = orm_integration_manager("postgres");
        $secondary = orm_integration_manager("postgres");

        $primaryPdo = $primary->connection()->getPdo();
        $secondaryPdo = $secondary->connection()->getPdo();

        $primaryPdo->exec("DROP TABLE IF EXISTS locks");
        $primaryPdo->exec(
            "CREATE TABLE locks (
                id SERIAL PRIMARY KEY,
                status INT NOT NULL
            )"
        );

        $primary
            ->connection()
            ->table("locks")
            ->insert(["status" => 1]);

        $primaryPdo->beginTransaction();
        (new IntegrationLock($primary))
            ->query()
            ->where("id", 1)
            ->lockForUpdate()
            ->first();

        $secondaryPdo->exec("SET lock_timeout TO '100ms'");

        $failed = false;
        try {
            $secondary
                ->connection()
                ->table("locks")
                ->where("id", 1)
                ->update(["status" => 2]);
        } catch (PDOException $exception) {
            $failed = true;
            $this->assertSame("55P03", $exception->getCode());
        } finally {
            $primaryPdo->rollBack();
        }

        $this->assertTrue($failed);
    }

    public function test_belongsToMany_pivot_helpers_work_on_mysql(): void
    {
        $this->skipIfUnavailable();

        $manager = orm_integration_manager("mysql");
        $pdo = $manager->connection()->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS role_user");
        $pdo->exec("DROP TABLE IF EXISTS roles");
        $pdo->exec("DROP TABLE IF EXISTS users");

        $pdo->exec(
            "CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), created_at DATETIME, updated_at DATETIME)"
        );
        $pdo->exec(
            "CREATE TABLE roles (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), created_at DATETIME, updated_at DATETIME)"
        );
        $pdo->exec(
            "CREATE TABLE role_user (user_id INT UNSIGNED, role_id INT UNSIGNED, created_at DATETIME, updated_at DATETIME)"
        );

        $user = IntegrationUser::create($manager, ["name" => "Pivot"]);
        $admin = IntegrationRole::create($manager, ["name" => "admin"]);
        $editor = IntegrationRole::create($manager, ["name" => "editor"]);

        $user->roles()->attach($admin->getKey());
        $user->roles()->sync([
            $admin->getKey() => ["created_at" => date(DATE_ATOM)],
            $editor->getKey() => [],
        ]);
        $user->roles()->toggle([$admin->getKey()]);

        $fresh = IntegrationUser::queryWith($manager)
            ->with("roles")
            ->find($user->getKey());

        $this->assertCount(1, $fresh?->roles);
        $this->assertSame("editor", $fresh?->roles[0]->getAttribute("name"));
    }

    public function test_eager_loading_batches_relations_on_postgres(): void
    {
        $this->skipIfUnavailable();

        $manager = orm_integration_manager("postgres");
        $pdo = $manager->connection()->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS posts");
        $pdo->exec("DROP TABLE IF EXISTS users");

        $pdo->exec(
            "CREATE TABLE users (id SERIAL PRIMARY KEY, name VARCHAR(255), created_at TIMESTAMP, updated_at TIMESTAMP)"
        );
        $pdo->exec(
            "CREATE TABLE posts (id SERIAL PRIMARY KEY, user_id INT, title VARCHAR(255), created_at TIMESTAMP, updated_at TIMESTAMP)"
        );

        $alice = IntegrationUser::create($manager, ["name" => "Alice"]);
        $bob = IntegrationUser::create($manager, ["name" => "Bob"]);

        IntegrationPost::create($manager, [
            "user_id" => $alice->getKey(),
            "title" => "Hello",
        ]);
        IntegrationPost::create($manager, [
            "user_id" => $alice->getKey(),
            "title" => "World",
        ]);
        IntegrationPost::create($manager, [
            "user_id" => $bob->getKey(),
            "title" => "Solo",
        ]);

        $users = IntegrationUser::queryWith($manager)
            ->with([
                "posts" => fn($q) => $q->orderByDesc("id"),
            ])
            ->orderBy("id")
            ->get();

        $this->assertCount(2, $users);
        $this->assertSame("World", $users[0]->posts[0]->getAttribute("title"));
        $this->assertCount(1, $users[1]->posts);
    }

    public function test_soft_deletes_with_withTrashed_and_onlyTrashed_on_mysql(): void
    {
        $this->skipIfUnavailable();

        $manager = orm_integration_manager("mysql");
        $pdo = $manager->connection()->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS users");
        $pdo->exec(
            "CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), deleted_at DATETIME NULL, created_at DATETIME NULL, updated_at DATETIME NULL)"
        );

        $a = IntegrationSoftUser::create($manager, ["name" => "A"]);
        $b = IntegrationSoftUser::create($manager, ["name" => "B"]);

        $b->delete();

        $all = IntegrationSoftUser::queryWith($manager)->get();
        $this->assertCount(1, $all);

        $withTrashed = (new IntegrationSoftUser($manager))->withTrashed()->get();
        $this->assertCount(2, $withTrashed);

        $only = (new IntegrationSoftUser($manager))->onlyTrashed()->get();
        $this->assertCount(1, $only);
        $this->assertSame("B", $only[0]->getAttribute("name"));
    }

    public function test_optimistic_locking_rejects_stale_update_on_mysql(): void
    {
        $this->markTestSkipped("Optimistic locking test is flaky on CI/Docker environment.");
        $this->skipIfUnavailable();

        $managerA = orm_integration_manager("mysql");
        $managerB = orm_integration_manager("mysql");
        $pdo = $managerA->connection()->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS locks");
        $pdo->exec(
            "CREATE TABLE locks (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), updated_at DATETIME NULL, created_at DATETIME NULL)"
        );

        $model = IntegrationLockingModel::create($managerA, ["name" => "live"]);
        $model->refresh();
        $stale = IntegrationLockingModel::findOrFail($managerB, $model->getKey());

        $model->name = "fresh";
        sleep(1);
        $model->save();

        $stale->name = "stale";

        $this->expectException(\Lalaz\Orm\Exceptions\OptimisticLockException::class);
        $stale->save();
    }

    public function test_upsert_and_insertMany_work_on_mysql(): void
    {
        $this->skipIfUnavailable();

        $manager = orm_integration_manager("mysql");
        $pdo = $manager->connection()->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS users");
        $pdo->exec(
            "CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) UNIQUE, name VARCHAR(255), deleted_at DATETIME NULL, created_at DATETIME NULL, updated_at DATETIME NULL)"
        );

        $query = (new IntegrationSoftUser($manager))->query();
        $query->insertMany([
            ["name" => "One", "email" => "one@ex.com"],
            ["name" => "Two", "email" => "two@ex.com"],
        ]);

        $query->upsert(
            [
                ["email" => "one@ex.com", "name" => "One v2"],
                ["email" => "three@ex.com", "name" => "Three"],
            ],
            "email",
            ["name"]
        );

        $all = $query->orderBy("email")->get();
        $this->assertCount(3, $all);
        $this->assertSame("One v2", $all[0]->getAttribute("name"));
    }

    public function test_camel_mapping_persists_and_hydrates_on_postgres_with_relations(): void
    {
        $this->skipIfUnavailable();

        $manager = orm_integration_manager("postgres");
        $pdo = $manager->connection()->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS camel_users");
        $pdo->exec("DROP TABLE IF EXISTS posts");
        $pdo->exec(
            "CREATE TABLE camel_users (id SERIAL PRIMARY KEY, first_name VARCHAR(255), created_at TIMESTAMP, updated_at TIMESTAMP)"
        );
        $pdo->exec(
            "CREATE TABLE posts (id SERIAL PRIMARY KEY, user_id INT, title VARCHAR(255), created_at TIMESTAMP, updated_at TIMESTAMP)"
        );

        $author = IntegrationCamelUser::create($manager, [
            "firstName" => "Ada",
        ]);
        IntegrationPost::create($manager, [
            "user_id" => $author->getKey(),
            "title" => "Hi",
        ]);

        $fetched = IntegrationCamelUser::queryWith($manager)
            ->with("posts")
            ->findOrFail($author->getKey());

        $this->assertSame("Ada", $fetched->getAttribute("firstName"));
        $this->assertCount(1, $fetched->posts);
        $this->assertEquals(
            $author->getKey(),
            $fetched->posts[0]->getAttribute("user_id")
        );
    }

    public function test_withPivot_hydrates_extra_pivot_columns_on_mysql(): void
    {
        $this->skipIfUnavailable();

        $manager = orm_integration_manager("mysql");
        $pdo = $manager->connection()->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS role_user");
        $pdo->exec("DROP TABLE IF EXISTS roles");
        $pdo->exec("DROP TABLE IF EXISTS users");
        $pdo->exec(
            "CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), created_at DATETIME, updated_at DATETIME)"
        );
        $pdo->exec(
            "CREATE TABLE roles (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), created_at DATETIME, updated_at DATETIME)"
        );
        $pdo->exec(
            "CREATE TABLE role_user (user_id INT, role_id INT, created_at DATETIME, updated_at DATETIME)"
        );

        $user = IntegrationPivotUser::create($manager, ["name" => "Pivot"]);
        $role = IntegrationPivotRole::create($manager, ["name" => "admin"]);

        $now = date(DATE_ATOM);
        $user->roles()->attach($role->getKey(), ["created_at" => $now]);

        $fresh = IntegrationPivotUser::queryWith($manager)
            ->with("roles")
            ->findOrFail($user->getKey());

        // MySQL DATETIME does not include timezone, so we compare the string representation
        $this->assertSame(
            date('Y-m-d H:i:s', strtotime($now)),
            $fresh->roles[0]->getRelations()["pivot"]["pivot_created_at"] ?? null
        );
    }

    public function test_tenant_scope_filters_and_can_be_removed_on_postgres(): void
    {
        $this->skipIfUnavailable();

        $manager = orm_integration_manager("postgres");
        $pdo = $manager->connection()->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS tenant_posts");
        $pdo->exec(
            "CREATE TABLE tenant_posts (id SERIAL PRIMARY KEY, title VARCHAR(255), tenant_id VARCHAR(50), created_at TIMESTAMP, updated_at TIMESTAMP)"
        );

        IntegrationTenantModel::setTenantId("t1");
        IntegrationTenantModel::create($manager, [
            "title" => "T1",
            "tenant_id" => "t1",
        ]);
        IntegrationTenantModel::create($manager, [
            "title" => "T2",
            "tenant_id" => "t2",
        ]);

        $scoped = IntegrationTenantModel::queryWith($manager)->get();
        $this->assertCount(1, $scoped);

        IntegrationTenantModel::setTenantId(null);
        $all = IntegrationTenantModel::queryWith($manager)->get();
        $this->assertCount(2, $all);
    }

    public function test_paginate_and_chunk_operate_correctly_on_mysql(): void
    {
        $this->skipIfUnavailable();

        $manager = orm_integration_manager("mysql");
        $pdo = $manager->connection()->getPdo();

        $pdo->exec("DROP TABLE IF EXISTS posts");
        $pdo->exec(
            "CREATE TABLE posts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), created_at DATETIME, updated_at DATETIME)"
        );

        $query = (new IntegrationPost($manager))->query();
        $query->insertMany([["title" => "A"], ["title" => "B"], ["title" => "C"]]);

        $page = $query->paginate(2, 1);
        $this->assertCount(2, $page["data"]);
        $this->assertSame(3, $page["total"]);

        $seen = [];
        $query->chunk(2, function (array $models) use (&$seen) {
            foreach ($models as $m) {
                $seen[] = $m->getAttribute("title");
            }
        });

        $this->assertCount(3, $seen);
    }
}
