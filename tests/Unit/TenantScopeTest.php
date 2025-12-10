<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Traits\HasTenantScope;

class TenantModel extends Model
{
    use HasTenantScope;

    protected ?string $table = "posts";
    protected array $fillable = ["title", "tenant_id"];
}

class TenantScopeTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, tenant_id TEXT, created_at TEXT, updated_at TEXT)",
        );

        TenantModel::setTenantId(null);
    }

    public function test_tenant_scope_filters_by_tenant_id(): void
    {
        TenantModel::setTenantId("tenant-a");

        TenantModel::create($this->manager, [
            "title" => "A1",
            "tenant_id" => "tenant-a",
        ]);
        TenantModel::create($this->manager, [
            "title" => "B1",
            "tenant_id" => "tenant-b",
        ]);

        $results = TenantModel::queryWith($this->manager)->get();
        $this->assertCount(1, $results);
        $this->assertSame("A1", $results[0]->getAttribute("title"));

        $all = TenantModel::queryWith($this->manager)->withoutGlobalScopes()->get();
        $this->assertCount(2, $all);
    }
}
