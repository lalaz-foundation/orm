<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Exceptions\OptimisticLockException;

class BatchUser extends Model
{
    protected ?string $table = "users";
    protected array $fillable = ["email", "name"];
    protected bool $usesOptimisticLocking = true;
    protected string $lockColumn = "updated_at";
}

class BatchOpsTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();

        $pdo->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, name TEXT, created_at TEXT, updated_at TEXT)",
        );
    }

    public function test_insert_many_stores_multiple_rows_in_one_call(): void
    {
        $result = BatchUser::queryWith($this->manager)->insertMany([
            ["email" => "a@example.com", "name" => "A"],
            ["email" => "b@example.com", "name" => "B"],
        ]);

        $this->assertTrue($result);
        $count = $this->manager->connection()->table("users")->count();
        $this->assertSame(2, $count);
    }

    public function test_upsert_updates_existing_row_based_on_unique_key(): void
    {
        $query = BatchUser::queryWith($this->manager);
        $query->insertMany([["email" => "a@example.com", "name" => "Old"]]);

        $query->upsert(
            [
                ["email" => "a@example.com", "name" => "New"],
                ["email" => "c@example.com", "name" => "C"],
            ],
            "email",
            ["name"],
        );

        $rows = $query->orderBy("email")->get();
        $this->assertCount(2, $rows);
        $this->assertSame("New", $rows[0]->getAttribute("name"));
    }

    public function test_update_where_and_delete_where_perform_targeted_mutations(): void
    {
        $query = BatchUser::queryWith($this->manager);
        $query->insertMany([
            ["email" => "a@example.com", "name" => "A"],
            ["email" => "b@example.com", "name" => "B"],
        ]);

        $affected = $query->updateWhere(
            ["email" => "b@example.com"],
            [
                "name" => "Bee",
            ],
        );
        $this->assertSame(1, $affected);

        $deleted = $query->deleteWhere(["email" => "a@example.com"]);
        $this->assertSame(1, $deleted);

        $remaining = $query->get();
        $this->assertCount(1, $remaining);
        $this->assertSame("Bee", $remaining[0]->getAttribute("name"));
    }

    public function test_optimistic_locking_prevents_stale_updates(): void
    {
        $query = BatchUser::queryWith($this->manager);
        $user = BatchUser::create($this->manager, [
            "email" => "lock@example.com",
            "name" => "First",
        ]);

        // Simulate concurrent fetch
        $stale = BatchUser::find($this->manager, $user->getKey());

        // Fresh update advances lock column
        $user->name = "Fresh";
        $user->save();
        $currentVersion = $user->getAttribute("updated_at");

        // Stale update should fail
        $stale->name = "Stale";
        // ensure stale still has old lock value
        $this->assertSame($currentVersion, $stale->getAttribute("updated_at"));
        // wait to guarantee a different timestamp
        usleep(1_100_000);

        $this->expectException(OptimisticLockException::class);
        $stale->save();
    }
}
