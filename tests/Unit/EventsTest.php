<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Events\EventDispatcher;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Validation\NullModelValidator;

class EventedModel extends Model
{
    protected ?string $table = "posts";
    protected array $fillable = ["title"];
    protected bool $usesOptimisticLocking = false;
}

class EventsTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $this->manager = new ModelManager(
            $this->manager->connection(),
            $this->manager->manager(),
            $this->manager->config(),
            new NullModelValidator(),
            new EventDispatcher(),
        );

        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, created_at TEXT, updated_at TEXT)",
        );
    }

    public function test_creating_and_created_fire_and_can_cancel(): void
    {
        $fired = [];
        $this->manager
            ->dispatcher()
            ->listen("model.creating", function ($model) use (&$fired) {
                $fired[] = "creating";
                return true;
            });
        $this->manager
            ->dispatcher()
            ->listen("model.created", function ($model) use (&$fired) {
                $fired[] = "created";
            });

        $model = EventedModel::create($this->manager, ["title" => "ok"]);

        $this->assertSame(["creating", "created"], $fired);
        $this->assertNotNull($model->getAttribute("id"));
    }

    public function test_updating_can_be_prevented(): void
    {
        $this->manager->dispatcher()->listen("model.updating", function ($model) {
            return false;
        });

        $model = EventedModel::create($this->manager, ["title" => "ok"]);
        $model->title = "blocked";
        $this->assertFalse($model->save());
    }

    public function test_deleting_and_restoring_fire_events(): void
    {
        $this->manager = new ModelManager(
            $this->manager->connection(),
            $this->manager->manager(),
            array_replace_recursive($this->manager->config(), [
                "soft_deletes" => ["enabled" => true, "deleted_at" => "deleted_at"],
            ]),
            new NullModelValidator(),
            new EventDispatcher(),
        );

        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS posts");
        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)",
        );

        $model = EventedModel::create($this->manager, ["title" => "ok"]);

        $fired = [];
        $this->manager
            ->dispatcher()
            ->listen("model.deleting", function () use (&$fired) {
                $fired[] = "deleting";
            });
        $this->manager
            ->dispatcher()
            ->listen("model.deleted", function () use (&$fired) {
                $fired[] = "deleted";
            });
        $this->manager
            ->dispatcher()
            ->listen("model.restoring", function () use (&$fired) {
                $fired[] = "restoring";
            });
        $this->manager
            ->dispatcher()
            ->listen("model.restored", function () use (&$fired) {
                $fired[] = "restored";
            });

        $model->delete();
        $model->restore();

        $this->assertSame(["deleting", "deleted", "restoring", "restored"], $fired);
    }
}
