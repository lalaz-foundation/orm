<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Exceptions\MassAssignmentException;
use Lalaz\Orm\Exceptions\ModelNotFoundException;

class FakeUser extends Model
{
    protected ?string $table = "users";
    protected array $fillable = ["name", "email"];
}

class ModelTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();

        $pdo->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, created_at TEXT, updated_at TEXT)",
        );
    }

    public function test_model_fill_and_save(): void
    {
        $user = FakeUser::build($this->manager, [
            "name" => "Ada",
            "email" => "ada@example.com",
        ]);

        $this->assertTrue($user->isDirty());
        $this->assertTrue($user->save());
        $this->assertFalse($user->isDirty());
        $this->assertNotNull($user->getAttribute("id"));
    }

    public function test_model_updates_and_dirty_tracking(): void
    {
        $user = FakeUser::build($this->manager, [
            "name" => "Ada",
            "email" => "ada@example.com",
        ]);
        $user->save();

        $user->name = "Updated";
        $this->assertTrue($user->isDirty());
        $this->assertTrue($user->save());
        $this->assertFalse($user->isDirty());
    }

    public function test_model_soft_delete_toggles_exists_flag(): void
    {
        $user = FakeUser::build($this->manager, [
            "name" => "Ada",
            "email" => "ada@example.com",
        ]);
        $user->save();

        $this->assertTrue($user->delete());
    }

    public function test_model_manager_wraps_transactions(): void
    {
        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE tx (id INTEGER PRIMARY KEY AUTOINCREMENT, val TEXT)",
        );

        try {
            $this->manager->transaction(function () use ($pdo): void {
                $pdo->exec("INSERT INTO tx (val) VALUES ('a')");
                throw new RuntimeException("boom");
            });
        } catch (RuntimeException) {
        }

        $count = $pdo->query("SELECT count(*) as total FROM tx")->fetchColumn();
        $this->assertSame(0, (int) $count);
    }

    public function test_fillable_enforcement_is_respected(): void
    {
        $this->expectException(MassAssignmentException::class);
        FakeUser::build($this->manager, [
            "name" => "Grace",
            "email" => "grace@example.com",
            "role" => "admin",
        ]);
    }

    public function test_force_fill_bypasses_fillable(): void
    {
        $user = FakeUser::build($this->manager, [
            "name" => "Grace",
            "email" => "grace@example.com",
        ]);

        $user->forceFill(["role" => "admin"]);
        $this->assertSame("admin", $user->getAttribute("role"));
    }

    public function test_find_or_fail_throws_model_not_found_exception(): void
    {
        $this->expectException(ModelNotFoundException::class);
        FakeUser::findOrFail($this->manager, 999);
    }
}
