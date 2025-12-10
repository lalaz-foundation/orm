<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Contracts\ModelValidatorInterface;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;

final class NoRuleModel extends Model
{
    protected ?string $table = "items";
    protected array $fillable = ["name"];

    protected function validationRules(string $operation): array
    {
        return []; // should skip validator entirely
    }
}

final class CountingValidator implements ModelValidatorInterface
{
    public int $calls = 0;

    public function validate(
        Model $model,
        array $data,
        array $rules,
        string $operation,
    ): void {
        $this->calls++;
    }
}

class ValidationSkipTest extends TestCase
{
    private CountingValidator $validator;
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new CountingValidator();
        $this->manager = orm_manager(validator: $this->validator);

        $pdo = $this->manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, created_at TEXT, updated_at TEXT)",
        );
    }

    public function test_validator_is_skipped_when_rules_are_empty(): void
    {
        $item = NoRuleModel::create($this->manager, ["name" => "ok"]);
        $this->assertNotNull($item->getAttribute("id"));
        $this->assertSame(0, $this->validator->calls);
    }
}
