<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Testing\Factory;

class FactoryUser extends Model
{
    protected ?string $table = "users";
    protected array $fillable = ["name", "tenant_id"];
}

class FactoryTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, tenant_id TEXT, created_at TEXT, updated_at TEXT)",
        );
    }

    public function test_factory_make_and_create_models(): void
    {
        $factory = Factory::new(
            $this->manager,
            FactoryUser::class,
            fn() => FactoryUser::build($this->manager, ["name" => "Ada"]),
        );

        /** @var FactoryUser $user */
        $user = $factory->build();
        $this->assertNull($user->getAttribute("id"));

        /** @var FactoryUser $created */
        $created = $factory->create();
        $this->assertNotNull($created->getAttribute("id"));
    }

    public function test_factory_states_and_count_apply_mutations(): void
    {
        $factory = Factory::new(
            $this->manager,
            FactoryUser::class,
            fn() => FactoryUser::build($this->manager, ["name" => "Seed"]),
        )
            ->state(
                fn(FactoryUser $user) => $user->forceFill(["tenant_id" => "t1"]),
            )
            ->count(2);

        $users = $factory->create();
        $this->assertCount(2, $users);
        $this->assertSame("t1", $users[0]->getAttribute("tenant_id"));
    }
}
