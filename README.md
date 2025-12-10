# Lalaz ORM

A lightweight, high-performance ActiveRecord-style ORM for PHP 8.3+ built on top of `lalaz/database`. It features explicit APIs, predictable behavior, and composability for building from minimal models to rich patterns with events, scopes, multi-tenancy, and more.

## Features

- **ActiveRecord Pattern**: Intuitive model-based database access with `Model` base class
- **Fluent Query Builder**: ModelQuery with selects, joins, pagination, batch mutations, locks
- **Relationships**: HasOne, HasMany, BelongsTo, BelongsToMany with eager loading and N+1 prevention
- **Attribute Casting**: int, float, bool, array, datetime, enums with timezone support
- **Timestamps**: Automatic `created_at`/`updated_at` management
- **Soft Deletes**: Non-destructive deletes with `deleted_at` column
- **Mass Assignment Protection**: Fillable/guarded attributes with rich error context
- **Events & Observers**: Full lifecycle hooks (creating, created, updating, updated, etc.)
- **Global/Local Scopes**: Automatic query constraints for multi-tenancy and more
- **Optimistic Locking**: Conflict detection for concurrent updates
- **Lazy Loading Guard**: Prevent N+1 queries at runtime in development
- **Model Factories**: Testing support with factory pattern
- **Validation Hooks**: Optional model validation before persistence
- **UUID/ULID Keys**: Support for non-incrementing primary keys
- **Camel↔Snake Mapping**: Automatic attribute name conversion
- **Type-Safe**: Full PHP 8.3+ type declarations

## Installation

```bash
composer require lalaz/orm
```

## Quick Start

### Defining a Model

```php
use Lalaz\Orm\Model;
use Lalaz\Orm\Relations\BelongsTo;
use Lalaz\Orm\Relations\HasMany;

final class Post extends Model
{
    protected ?string $table = 'posts';
    protected array $fillable = ['title', 'body', 'published'];
    protected array $casts = [
        'published' => 'bool',
        'published_at' => 'datetime',
    ];
    
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

### Basic CRUD Operations

```php
use Lalaz\Orm\ModelManager;

// Create
$post = Post::create($manager, [
    'title' => 'Hello World',
    'body' => 'Welcome to Lalaz ORM!',
    'published' => true,
]);

// Read
$post = Post::find($manager, 1);
$post = Post::findOrFail($manager, 1);
$posts = Post::all($manager);

// Update
$post->title = 'Updated Title';
$post->save();

// Delete
$post->delete();
```

### Querying

```php
// Fluent queries
$posts = Post::queryWith($manager)
    ->where('published', true)
    ->orderBy('created_at', 'desc')
    ->get();

// With eager loading (prevents N+1)
$posts = Post::queryWith($manager)
    ->with(['author', 'comments'])
    ->where('published', true)
    ->paginate(15);

// Pagination returns metadata
// [
//     'data' => [...],       // Array of Post models
//     'total' => 100,        // Total records
//     'per_page' => 15,
//     'current_page' => 1,
//     'last_page' => 7,
//     'from' => 1,
//     'to' => 15,
// ]
```

## Relationships

### Defining Relationships

```php
class User extends Model
{
    protected ?string $table = 'users';
    protected array $fillable = ['name', 'email'];
    
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
    
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
    
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}

class Post extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

### Eager Loading

```php
// Prevent N+1 with eager loading
$users = User::queryWith($manager)
    ->with(['posts', 'profile', 'roles'])
    ->get();

// With constraints
$users = User::queryWith($manager)
    ->with([
        'posts' => function ($query) {
            $query->where('published', true);
        }
    ])
    ->get();
```

### Many-to-Many Operations

```php
// Attach roles
$user->roles()->attach($roleId, ['assigned_at' => now()]);

// Detach roles
$user->roles()->detach($roleId);

// Sync roles (replaces all)
$user->roles()->sync([$roleId1, $roleId2]);

// Toggle roles
$user->roles()->toggle([$roleId]);
```

## Model Events

### Available Events

| Event | Timing |
|-------|--------|
| `creating` | Before insert |
| `created` | After insert |
| `updating` | Before update |
| `updated` | After update |
| `saving` | Before insert/update |
| `saved` | After insert/update |
| `deleting` | Before delete |
| `deleted` | After delete |
| `restoring` | Before restore (soft delete) |
| `restored` | After restore (soft delete) |

### Listening to Events

```php
// Via dispatcher
$manager->dispatcher()->listen('model.creating', function ($model) {
    $model->slug = Str::slug($model->title);
});

// Via observer class
class PostObserver
{
    public function creating(Post $post): void
    {
        $post->slug = Str::slug($post->title);
    }
    
    public function deleted(Post $post): void
    {
        // Cleanup after delete
    }
}

// Register in model
class Post extends Model
{
    protected function observers(): array
    {
        return [PostObserver::class];
    }
}
```

## Soft Deletes

```php
class Post extends Model
{
    protected bool $softDeletes = true;
    protected string $deletedAtColumn = 'deleted_at';
}

// Soft delete
$post->delete(); // Sets deleted_at, doesn't remove row

// Query including soft deleted
$posts = $post->withTrashed()->get();

// Query only soft deleted
$posts = $post->onlyTrashed()->get();

// Restore soft deleted
$post->restore();

// Force delete (permanent)
$post->forceDelete();
```

## Attribute Casting

```php
class Post extends Model
{
    protected array $casts = [
        'published' => 'bool',
        'views' => 'int',
        'rating' => 'float',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'status' => PostStatus::class, // Enum
    ];
}

// Values are automatically cast
$post->published;     // bool
$post->views;         // int
$post->published_at;  // DateTimeImmutable
$post->status;        // PostStatus enum
```

## Global Scopes

```php
// Add global scope
User::addGlobalScope('active', function ($builder, $model) {
    $builder->where('active', true);
});

// Query without global scope
$users = User::queryWith($manager)
    ->withoutGlobalScopes(['active'])
    ->get();

// Multi-tenant scope (built-in)
class Post extends Model
{
    use HasTenantScope;
    
    protected string $tenantColumn = 'tenant_id';
}
```

## Local Scopes

```php
class Post extends Model
{
    public function scopePublished(ModelQuery $query): void
    {
        $query->where('published', true);
    }
    
    public function scopeRecent(ModelQuery $query, int $days = 7): void
    {
        $query->where('created_at', '>=', 
            (new DateTime())->modify("-{$days} days")->format(DATE_ATOM)
        );
    }
}

// Use scopes
$posts = Post::queryWith($manager)
    ->published()
    ->recent(30)
    ->get();
```

## Optimistic Locking

```php
class Post extends Model
{
    protected bool $usesOptimisticLocking = true;
    protected string $lockColumn = 'updated_at';
}

// If another process updated the record, throws:
// OptimisticLockException
$post->save();
```

## Batch Operations

```php
// Insert many
$query->insertMany([
    ['title' => 'Post 1', 'body' => '...'],
    ['title' => 'Post 2', 'body' => '...'],
]);

// Upsert (insert or update)
$query->upsert(
    [['id' => 1, 'title' => 'Updated']],
    ['id'],           // Unique columns
    ['title']         // Columns to update
);

// Batch update
$query->updateWhere(['status' => 'draft'], ['status' => 'published']);

// Batch delete
$query->deleteWhere(['status' => 'archived']);
```

## Chunking Results

```php
// Process in batches
Post::queryWith($manager)->chunk(100, function ($posts, $page) {
    foreach ($posts as $post) {
        // Process post
    }
    
    return true; // Continue; return false to stop
});

// Iterate each model
Post::queryWith($manager)->each(function ($post) {
    // Process post
    return true; // Continue; return false to stop
}, count: 100);
```

## Model Validation

```php
class Post extends Model
{
    protected function validationRules(string $operation): array
    {
        return [
            'title' => 'required|min:5|max:255',
            'body' => 'required',
        ];
    }
}

// Throws ValidationException if invalid
$post->save();
```

## Serialization

```php
class User extends Model
{
    protected array $visible = ['id', 'name', 'email'];
    protected array $hidden = ['password', 'remember_token'];
}

// Convert to array
$array = $user->toArray();

// Convert to JSON
$json = $user->toJson();
```

## Configuration

```php
// config/orm.php
return [
    'timestamps' => [
        'enabled' => true,
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ],
    'soft_deletes' => [
        'enabled' => false,
        'deleted_at' => 'deleted_at',
    ],
    'enforce_fillable' => true,
    'mass_assignment' => [
        'throw_on_violation' => true,
    ],
    'lazy_loading' => [
        'prevent' => false,
        'allow_testing' => true,
        'allowed_relations' => [],
    ],
    'validation' => [
        'enabled' => true,
    ],
    'dates' => [
        'format' => DATE_ATOM,
        'timezone' => null,
    ],
    'naming' => [
        'hydrate' => null, // 'camel' for camelCase attributes
    ],
];
```

## Testing

### Using In-Memory Database

```php
use PHPUnit\Framework\TestCase;

class PostTest extends TestCase
{
    private ModelManager $manager;
    
    protected function setUp(): void
    {
        // Create in-memory SQLite manager
        $this->manager = orm_manager();
        $pdo = $this->manager->connection()->getPdo();
        
        // Create tables
        $pdo->exec('CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            body TEXT,
            created_at TEXT,
            updated_at TEXT
        )');
    }
    
    public function test_create_post(): void
    {
        $post = Post::create($this->manager, [
            'title' => 'Test',
            'body' => 'Content',
        ]);
        
        $this->assertNotNull($post->getAttribute('id'));
    }
}
```

### Model Factories

```php
use Lalaz\Orm\Testing\Factory;

class PostFactory extends Factory
{
    protected function definition(): array
    {
        return [
            'title' => 'Test Post ' . random_int(1, 1000),
            'body' => 'Lorem ipsum...',
            'published' => false,
        ];
    }
}

// Usage
$post = PostFactory::new($manager)->create();
$posts = PostFactory::new($manager)->count(5)->create();
```

## Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test file
./vendor/bin/phpunit tests/Unit/ModelTest.php

# Run specific test
./vendor/bin/phpunit --filter test_model_fill_and_save
```

## Requirements

- PHP 8.3 or higher
- lalaz/database package

## Documentation

- [Getting Started](docs/getting-started.md) - Installation and basic setup
- [Models & Attributes](docs/models.md) - Model definition and configuration
- [Relationships](docs/relationships-and-eager-loading.md) - Defining and using relationships
- [Querying](docs/querying.md) - Query building and pagination
- [Events & Observers](docs/events-and-observers.md) - Lifecycle hooks
- [Multi-Tenancy](docs/multi-tenancy.md) - Tenant scope implementation
- [Validation](docs/validation.md) - Model validation hooks
- [Testing](docs/testing-and-factories.md) - Testing strategies and factories
- [API Reference](docs/reference-model.md) - Complete API documentation

## License

MIT License. See [LICENSE](LICENSE) for details.
