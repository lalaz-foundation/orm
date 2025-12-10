<?php declare(strict_types=1);

namespace Lalaz\Orm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;

class PaginationPost extends Model
{
    protected ?string $table = "posts";
    protected array $fillable = ["title"];
}

class PaginationTest extends TestCase
{
    private ModelManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();

        $pdo->exec(
            "CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, created_at TEXT, updated_at TEXT)",
        );

        foreach (range(1, 7) as $i) {
            PaginationPost::create($this->manager, ["title" => "Post {$i}"]);
        }
    }

    public function test_paginate_returns_metadata_and_models(): void
    {
        $result = PaginationPost::queryWith($this->manager)->paginate(
            perPage: 3,
            page: 2,
        );

        $this->assertSame(7, $result["total"]);
        $this->assertSame(3, $result["per_page"]);
        $this->assertSame(2, $result["current_page"]);
        $this->assertSame(3, $result["last_page"]);
        $this->assertSame(4, $result["from"]);
        $this->assertSame(6, $result["to"]);
        $this->assertCount(3, $result["data"]);
        $this->assertInstanceOf(PaginationPost::class, $result["data"][0]);
    }

    public function test_chunk_iterates_in_batches_and_can_stop_early(): void
    {
        $seen = [];
        PaginationPost::queryWith($this->manager)->chunk(
            2,
            function (array $models, int $page) use (&$seen) {
                foreach ($models as $model) {
                    $seen[] = $model->getAttribute("title");
                }

                // stop after first two batches
                return $page < 2;
            },
        );

        $this->assertSame([
            "Post 1",
            "Post 2",
            "Post 3",
            "Post 4",
        ], $seen);
    }

    public function test_each_iterates_every_model_until_callback_stops(): void
    {
        $count = 0;
        PaginationPost::queryWith($this->manager)->each(
            function (PaginationPost $post) use (&$count) {
                $count++;
                if ($post->getAttribute("title") === "Post 3") {
                    return false;
                }
                return null;
            },
            2,
        );

        $this->assertSame(3, $count);
    }
}
