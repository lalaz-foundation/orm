# Getting Started

## Install

```bash
composer require lalaz/orm
```

`lalaz/orm` depends on `lalaz/database` for connections, pooling, and the query builder.

## Bootstrapping

```php
use Lalaz\Database\ConnectionManager;
use Lalaz\Database\Connection;
use Lalaz\Orm\ModelManager;
use Lalaz\Orm\Events\EventDispatcher;

$dbConfig = require __DIR__.'/config/database.php';
$manager   = new ConnectionManager($dbConfig);
$connection = new Connection($manager);

$orm = new ModelManager(
    $connection,
    $manager,
    require __DIR__.'/config/orm.php',
    validator: null,           // optional ModelValidatorInterface
    dispatcher: new EventDispatcher()
);
```

When using the framework container, register `OrmServiceProvider` and it will resolve `ModelManager` with your config automatically (and inject a validator if bound).

## Defining a Model

```php
use Lalaz\Orm\Model;

final class Post extends Model
{
    protected ?string $table = 'posts';           // optional; defaults to plural snake of class
    protected array $fillable = ['title', 'body'];
    protected array $casts = ['published_at' => 'datetime'];
}
```

### Creating and querying

```php
$post = Post::create($orm, ['title' => 'Hello', 'body' => '...']);

$found = Post::find($orm, $post->id);

$posts = Post::queryWith($orm)
    ->where('published', true)
    ->with('author')
    ->orderBy('published_at', 'desc')
    ->paginate(10);
```

### Transactions

```php
$orm->transaction(function () use ($orm) {
    $user = User::create($orm, ['email' => 'a@b.com']);
    $profile = Profile::create($orm, ['user_id' => $user->id]);
});
```

### Soft deletes and timestamps

Enable globally via config or per‑model properties. Soft deletes add `deleted_at` filters automatically; use `withTrashed()`/`onlyTrashed()` to include them.
