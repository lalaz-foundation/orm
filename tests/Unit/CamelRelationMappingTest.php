<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;

class CamelAuthor extends Model
{
    protected ?string $table = "authors";
    protected array $fillable = ["firstName"];
    protected bool $useCamelAttributes = true;

    public function posts(): mixed
    {
        return $this->hasMany(CamelRelationPost::class, "author_id");
    }
}

class CamelRelationPost extends Model
{
    protected ?string $table = "posts";
    protected array $fillable = ["authorId", "title"];
    protected bool $useCamelAttributes = true;
}

class CamelRelationMappingTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();

        $pdo->exec(
            "CREATE TABLE authors (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT, created_at TEXT, updated_at TEXT)",
        );
        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, author_id INTEGER, title TEXT, created_at TEXT, updated_at TEXT)",
        );

        $now = date(DATE_ATOM);
        $pdo->exec(
            "INSERT INTO authors (first_name, created_at, updated_at) VALUES ('Ada', '$now', '$now')",
        );
        $authorId = (int) $pdo->lastInsertId();
        $pdo->exec(
            "INSERT INTO posts (author_id, title, created_at, updated_at) VALUES ($authorId, 'Camel', '$now', '$now')",
        );
    }

    public function test_camel_mapping_works_with_eager_loaded_relations(): void
    {
        $authors = CamelAuthor::queryWith($this->manager)->with("posts")->get();

        $this->assertCount(1, $authors);
        $author = $authors[0];
        $this->assertSame("Ada", $author->getAttribute("firstName"));
        $posts = $author->getRelations()["posts"] ?? [];
        $this->assertCount(1, $posts);
        $this->assertSame($author->getKey(), $posts[0]->getAttribute("authorId"));
    }
}
