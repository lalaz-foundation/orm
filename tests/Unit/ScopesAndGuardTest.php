<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\Query\ModelQuery;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Exceptions\MassAssignmentException;

class ScopedUser extends Model
{
    protected ?string $table = "users";
    protected array $fillable = ["name", "active"];
    protected array $guarded = ["secret"];

    public function scopeActive(ModelQuery $query): void
    {
        $query->where("active", 1);
    }
}

class GlobalScopedUser extends ScopedUser
{
    protected static bool $booted = false;

    public function __construct(ModelManager $manager)
    {
        parent::__construct($manager);

        if (!self::$booted) {
            self::$booted = true;
            static::addGlobalScope("published", function ($builder): void {
                $builder->where("active", 1);
            });
        }
    }
}

class ScopesAndGuardTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();

        $pdo->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INTEGER, secret TEXT, created_at TEXT, updated_at TEXT)",
        );

        $pdo->exec(
            "INSERT INTO users (name, active, secret, created_at, updated_at) VALUES ('Ada', 1, 'yes', datetime('now'), datetime('now'))",
        );
        $pdo->exec(
            "INSERT INTO users (name, active, secret, created_at, updated_at) VALUES ('Bob', 0, 'no', datetime('now'), datetime('now'))",
        );
    }

    public function test_local_scopes_are_applied_via_magic_call(): void
    {
        $active = ScopedUser::queryWith($this->manager)->active()->get();
        $this->assertCount(1, $active);
        $this->assertSame("Ada", $active[0]->getAttribute("name"));
    }

    public function test_global_scopes_apply_automatically_and_can_be_removed(): void
    {
        $all = GlobalScopedUser::queryWith($this->manager)->get();
        $this->assertCount(1, $all);

        $unscoped = GlobalScopedUser::queryWith($this->manager)
            ->withoutGlobalScopes()
            ->get();
        $this->assertCount(2, $unscoped);
    }

    public function test_guarded_attributes_reject_mass_assignment(): void
    {
        $this->expectException(MassAssignmentException::class);
        ScopedUser::create($this->manager, [
            "name" => "Eve",
            "secret" => "hack",
        ]);
    }

    public function test_guarded_can_be_skipped_with_config(): void
    {
        $manager = orm_manager([
            "mass_assignment" => ["throw_on_violation" => false],
        ]);

        $pdo = $manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INTEGER, secret TEXT, created_at TEXT, updated_at TEXT)",
        );

        $user = ScopedUser::create($manager, [
            "name" => "Eve",
            "secret" => "hack",
        ]);

        $this->assertNull($user->getAttribute("secret"));
    }
}
