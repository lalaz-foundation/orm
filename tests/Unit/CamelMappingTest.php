<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;

class CamelUser extends Model
{
    protected ?string $table = "users";
    protected array $fillable = ["firstName"];
}

class CamelMappingTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager([
            "naming" => ["hydrate" => "camel"],
        ]);

        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT, created_at TEXT, updated_at TEXT)",
        );
    }

    public function test_camel_mapping_persists_snake_columns_and_hydrates_camel_attributes(): void
    {
        $user = CamelUser::create($this->manager, ["firstName" => "Ada"]);

        // DB uses snake_case
        $row = $this->manager
            ->connection()
            ->query("SELECT first_name FROM users WHERE id = {$user->getKey()}")
            ->fetch();
        $this->assertSame("Ada", $row["first_name"] ?? null);

        // Model uses camelCase in memory/serialization
        $fresh = CamelUser::findOrFail($this->manager, $user->getKey());
        $this->assertSame("Ada", $fresh->getAttribute("firstName"));
        $this->assertArrayHasKey("firstName", $fresh->toArray());
    }
}
