<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Traits\HasTenantScope;

final class AltTenantModel extends Model
{
    use HasTenantScope;

    protected ?string $table = "posts";
    protected array $fillable = ["title", "account_id"];
}

class TenantScopeConfigTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, account_id TEXT, created_at TEXT, updated_at TEXT)",
        );

        AltTenantModel::setTenantId(null);
        AltTenantModel::setTenantColumn("account_id");
    }

    public function test_custom_tenant_column_and_disabling_scope_work(): void
    {
        AltTenantModel::setTenantId("acc-1");
        AltTenantModel::create($this->manager, [
            "title" => "A1",
            "account_id" => "acc-1",
        ]);
        AltTenantModel::create($this->manager, [
            "title" => "B1",
            "account_id" => "acc-2",
        ]);

        $scoped = AltTenantModel::queryWith($this->manager)->get();
        $this->assertCount(1, $scoped);
        $this->assertSame("A1", $scoped[0]->getAttribute("title"));

        AltTenantModel::setTenantId(null);
        $all = AltTenantModel::queryWith($this->manager)->get();
        $this->assertCount(2, $all);
    }
}
