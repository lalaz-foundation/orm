<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Exceptions\InvalidRelationException;
use Lalaz\Orm\Exceptions\MassAssignmentException;
use Lalaz\Orm\Exceptions\ModelNotFoundException;
use Lalaz\Orm\Exceptions\RelationNotFoundException;
use Lalaz\Orm\Model;

class ErrorContextUser extends Model
{
    protected array $guarded = ["secret"];
    protected ?string $table = "users";
}

class InvalidRelationModel extends Model
{
    protected ?string $table = "invalids";

    public function bogus(): string
    {
        return "nope";
    }
}

class ErrorContextTest extends TestCase
{
    public function test_includes_table_in_relation_not_found_exceptions(): void
    {
        $manager = orm_manager();

        $this->expectException(RelationNotFoundException::class);
        $this->expectExceptionMessage("table users");
        ErrorContextUser::queryWith($manager)->with("profile");
    }

    public function test_throws_when_relation_method_does_not_return_a_relation(): void
    {
        $manager = orm_manager();
        $model = new InvalidRelationModel($manager);

        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage("Relation [bogus]");
        $model->bogus;
    }

    public function test_reports_table_in_model_not_found_exceptions(): void
    {
        $manager = orm_manager();
        $manager
            ->connection()
            ->query(
                "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)",
            );

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage("table users");
        ErrorContextUser::queryWith($manager)->findOrFail(99);
    }

    public function test_mass_assignment_exception_exposes_table_and_guard_lists(): void
    {
        $manager = orm_manager();

        $this->expectException(MassAssignmentException::class);
        $this->expectExceptionMessage("Guarded: secret");
        ErrorContextUser::build($manager, ["secret" => "boom"]);
    }
}
