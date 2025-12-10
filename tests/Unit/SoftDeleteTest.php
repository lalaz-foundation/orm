<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;

class SoftUser extends Model
{
    protected ?string $table = "users";
    protected array $fillable = ["name"];
}

class SoftDeleteTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager([
            "soft_deletes" => [
                "enabled" => true,
                "deleted_at" => "deleted_at",
            ],
        ]);

        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)",
        );
    }

    public function test_soft_deleted_rows_are_hidden_by_default(): void
    {
        $user = SoftUser::build($this->manager, ["name" => "Ada"]);
        $user->save();

        $user->delete();
        $this->assertNotNull($user->getAttribute("deleted_at"));

        $visible = (new SoftUser($this->manager))->query()->first();
        $this->assertNull($visible);

        $withTrashed = (new SoftUser($this->manager))
            ->withTrashed()
            ->first();
        $this->assertNotNull($withTrashed);

        $onlyTrashed = (new SoftUser($this->manager))
            ->onlyTrashed()
            ->first();
        $this->assertNotNull($onlyTrashed);
    }

    public function test_restore_re_enables_default_visibility(): void
    {
        $user = SoftUser::build($this->manager, ["name" => "Ada"]);
        $user->save();
        $user->delete();

        $restored = (new SoftUser($this->manager))
            ->withTrashed()
            ->first();
        $restored->restore();

        $visible = (new SoftUser($this->manager))->query()->first();
        $this->assertNotNull($visible);
        $this->assertNull($visible->getAttribute("deleted_at"));
    }
}
