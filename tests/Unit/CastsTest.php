<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;

enum StatusEnum: string
{
    case ACTIVE = "active";
    case INACTIVE = "inactive";
}

class CastedModel extends Model
{
    protected ?string $table = "casts";
    protected array $fillable = ["name", "status", "published_at", "meta"];
    protected array $casts = [
        "status" => StatusEnum::class,
        "published_at" => "datetime",
        "meta" => "json",
    ];
    protected string $dateFormat = "Y-m-d H:i:s";
    protected ?string $timezone = "UTC";

    public function getNameAttribute(mixed $value): mixed
    {
        return strtoupper((string) $value);
    }

    public function setNameAttribute(mixed $value): void
    {
        // upper-case on write
        $this->attributes["name"] = strtoupper((string) $value);
    }
}

class NoTimestampModel extends Model
{
    protected ?string $table = "plain";
    protected bool $timestamps = false;
    protected bool $useConfigTimestamps = false;
    protected array $fillable = ["name"];
}

class CustomTimestampModel extends Model
{
    protected ?string $table = "custom_times";
    protected array $fillable = ["name"];
    protected string $createdAtColumn = "created_on";
    protected string $updatedAtColumn = "updated_on";
    protected string $dateFormat = "Y-m-d H:i";
    protected bool $useConfigTimestamps = false;
}

class UuidModel extends Model
{
    protected ?string $table = "uuids";
    protected array $fillable = ["name"];
    protected bool $incrementing = false;
    protected string $keyType = "uuid";
}

class UlidModel extends Model
{
    protected ?string $table = "ulids";
    protected array $fillable = ["name"];
    protected bool $incrementing = false;
    protected string $keyType = "ulid";
    protected string $primaryKey = "id";
}

class CastsTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();

        $pdo->exec("CREATE TABLE casts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            status TEXT,
            published_at TEXT,
            meta TEXT,
            created_at TEXT,
            updated_at TEXT
        )");

        $pdo->exec(
            "CREATE TABLE plain (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)",
        );

        $pdo->exec(
            "CREATE TABLE custom_times (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, created_on TEXT, updated_on TEXT)",
        );

        $pdo->exec(
            "CREATE TABLE uuids (id TEXT PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT)",
        );

        $pdo->exec(
            "CREATE TABLE ulids (id TEXT PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT)",
        );
    }

    public function test_casts_datetime_and_enum_with_timezone(): void
    {
        $published = "2024-01-01 12:00:00";

        $model = CastedModel::create($this->manager, [
            "name" => "ada",
            "status" => "active",
            "published_at" => $published,
            "meta" => ["x" => 1],
        ]);

        $this->assertSame("ADA", $model->name);
        $this->assertInstanceOf(StatusEnum::class, $model->getAttribute("status"));
        $this->assertSame("active", $model->getAttribute("status")->value);
        $this->assertInstanceOf(DateTimeImmutable::class, $model->getAttribute("published_at"));
        $this->assertSame(["x" => 1], $model->getAttribute("meta"));
    }

    public function test_timestamps_can_be_disabled_per_model(): void
    {
        $model = NoTimestampModel::create($this->manager, ["name" => "plain"]);
        $this->assertNull($model->getAttribute("created_at"));
        $this->assertNull($model->getAttribute("updated_at"));
    }

    public function test_timestamp_column_names_can_be_customized(): void
    {
        $model = CustomTimestampModel::create($this->manager, ["name" => "custom"]);

        $this->assertNotNull($model->getAttribute("created_on"));
        $this->assertNotNull($model->getAttribute("updated_on"));
    }

    public function test_non_incrementing_keys_generate_uuid_ulid(): void
    {
        $uuid = UuidModel::create($this->manager, ["name" => "u"]);
        $ulid = UlidModel::create($this->manager, ["name" => "l"]);

        $this->assertMatchesRegularExpression('/^[0-9a-f\-]{36}$/i', $uuid->getAttribute("id"));
        $this->assertSame(26, strlen($ulid->getAttribute("id")));
    }
}
