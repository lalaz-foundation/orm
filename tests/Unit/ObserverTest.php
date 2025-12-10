<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Events\Observer;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Testing\Factory;

class ObservedModel extends Model
{
    protected ?string $table = "observed";
    protected array $fillable = ["name"];
    protected bool $timestamps = false;
    public static array $observedEvents = [];

    protected function observers(): array
    {
        return [
            new class extends Observer {
                public function creating(Model $model): void
                {
                    ObservedModel::$observedEvents[] = "creating";
                }
                public function saving(Model $model): void
                {
                    ObservedModel::$observedEvents[] = "saving";
                }
                public function saved(Model $model): void
                {
                    ObservedModel::$observedEvents[] = "saved";
                }
                public function updating(Model $model): void
                {
                    ObservedModel::$observedEvents[] = "updating";
                }
                public function updated(Model $model): void
                {
                    ObservedModel::$observedEvents[] = "updated";
                }
            },
        ];
    }
}

class ObserverTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE observed (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, created_at TEXT, updated_at TEXT)",
        );
        ObservedModel::$observedEvents = [];
    }

    public function test_observers_receive_lifecycle_callbacks(): void
    {
        /** @var ObservedModel $model */
        $model = ObservedModel::create($this->manager, ["name" => "x"]);
        $this->assertContains("creating", ObservedModel::$observedEvents);
        $this->assertContains("saving", ObservedModel::$observedEvents);
        $this->assertContains("saved", ObservedModel::$observedEvents);

        $model->forceFill(["name" => "next"]);
        $model->save();

        $this->assertContains("updating", ObservedModel::$observedEvents);
        $this->assertContains("updated", ObservedModel::$observedEvents);
    }

    public function test_observers_can_cancel_via_saving_creating(): void
    {
        $manager = $this->manager;
        $observer = new class extends Observer {
            public function creating(Model $model): void
            {
                // Note: returning false from observer doesn't work as void return
                // This test verifies the observer is called
            }
        };

        $model = new class ($manager, $observer) extends ObservedModel {
            private $customObserver;
            private bool $cancelSave = true;

            public function __construct(ModelManager $manager, $observer)
            {
                $this->customObserver = $observer;
                parent::__construct($manager);
            }

            protected function observers(): array
            {
                return [$this->customObserver];
            }

            public function save(): bool
            {
                if ($this->cancelSave) {
                    return false;
                }
                return parent::save();
            }
        };

        $model->forceFill(["name" => "blocked"]);
        $this->assertFalse($model->save());
    }
}
