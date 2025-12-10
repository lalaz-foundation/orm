<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Contracts\ModelValidatorInterface;
use Lalaz\Orm\Exceptions\ValidationException;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;

final class ValidatedUser extends Model
{
    protected ?string $table = "validated_users";
    protected array $fillable = ["name", "email", "fail"];

    protected function validationRules(string $operation): array
    {
        return [
            "create" => ["name" => "required"],
            "update" => ["email" => "required"],
        ][$operation] ?? [];
    }
}

final class RecordingValidator implements ModelValidatorInterface
{
    /** @var array<int, string> */
    public array $operations = [];

    public function validate(
        Model $model,
        array $data,
        array $rules,
        string $operation,
    ): void {
        $this->operations[] = $operation;

        if (($data["fail"] ?? false) === true) {
            throw ValidationException::failed(["name" => ["invalid"]]);
        }
    }
}

class ValidationHookTest extends TestCase
{
    private function createTable(ModelManager $manager): void
    {
        $pdo = $manager->connection()->getPdo();
        $pdo->exec(
            "CREATE TABLE validated_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, fail INTEGER, created_at TEXT, updated_at TEXT)"
        );
    }

    public function test_runs_validator_on_create_and_update(): void
    {
        $validator = new RecordingValidator();
        $manager = orm_manager(validator: $validator);
        $this->createTable($manager);

        $user = ValidatedUser::build($manager, ["name" => "Greg"]);
        $user->save();

        $user->forceFill(["email" => "me@lalaz.dev"]);
        $user->save();

        $this->assertSame(["create", "update"], $validator->operations);
    }

    public function test_skips_validation_when_disabled_in_config(): void
    {
        $validator = new RecordingValidator();
        $manager = orm_manager(
            ["validation" => ["enabled" => false]],
            validator: $validator,
        );
        $this->createTable($manager);

        $user = ValidatedUser::build($manager, ["name" => "Greg"]);
        $user->save();

        $this->assertSame([], $validator->operations);
    }

    public function test_throws_when_validator_fails(): void
    {
        $this->expectException(ValidationException::class);

        $validator = new RecordingValidator();
        $manager = orm_manager(validator: $validator);
        $this->createTable($manager);

        $user = ValidatedUser::build($manager, ["fail" => true]);
        $user->save();
    }
}
