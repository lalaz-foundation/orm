# Lalaz ORM

A powerful ActiveRecord-style ORM for PHP applications built on top of `lalaz/database`.

## Overview

Lalaz ORM provides an intuitive ActiveRecord implementation for database access. Models represent database tables, with rich support for relationships, casting, events, and more. Built on the lalaz/database query builder, it offers both simplicity and power.

## Features

- **ActiveRecord Pattern** - Intuitive model-based database access
- **Relationships** - HasOne, HasMany, BelongsTo, BelongsToMany with eager loading
- **Timestamps** - Automatic `created_at`/`updated_at` management
- **Soft Deletes** - Non-destructive delete with `deleted_at`
- **Attribute Casting** - int, float, bool, array, datetime, enums
- **Mass Assignment Protection** - Fillable and guarded attributes
- **Model Validation** - Integrated validation hooks
- **Events & Observers** - Lifecycle event hooks
- **Global Scopes** - Automatic query constraints (e.g., tenancy)
- **Optimistic Locking** - Conflict detection for concurrent updates
- **Lazy Loading Guard** - Prevent N+1 queries at runtime
- **Model Factories** - Testing support with factories

## Quick Start

```php
use Lalaz\Orm\Model;

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
    
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}

// Create a new post
$post = Post::create($orm, [
    'title' => 'Hello World',
    'body' => 'Welcome to Lalaz ORM!',
]);

// Find by ID
$post = Post::find($orm, 1);
$post = Post::findOrFail($orm, 1);

// Query with eager loading
$posts = Post::queryWith($orm)
    ->where('published', true)
    ->with(['author', 'comments'])
    ->orderBy('published_at', 'desc')
    ->paginate(15);
```

## Relationships

```php
class User extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
    
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
}

class Post extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}

// Eager loading prevents N+1
$users = User::queryWith($orm)
    ->with(['posts', 'profile'])
    ->get();
```

## Model Lifecycle Events

```php
// Register listeners
$orm->dispatcher()->listen('model.creating', function ($model) {
    // Before insert
});

$orm->dispatcher()->listen('model.saved', function ($model) {
    // After insert or update
});

// Or use observers
class PostObserver extends Observer
{
    public function creating($model)
    {
        $model->slug = Str::slug($model->title);
    }
    
    public function deleted($model)
    {
        // Cleanup after delete
    }
}
```

## Available Events

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
    ],
    'validation' => [
        'enabled' => true,
    ],
];
```

## Pagination

```php
$result = Post::queryWith($orm)
    ->where('published', true)
    ->paginate(15, 1);

// Returns:
// [
//     'data' => [...],          // Array of Post models
//     'total' => 100,           // Total records
//     'per_page' => 15,
//     'current_page' => 1,
//     'last_page' => 7,
//     'from' => 1,
//     'to' => 15,
// ]
```

## Next Steps

- [Getting Started](getting-started.md) - Installation and setup
- [Models](models.md) - Model definition and attributes
- [Relationships](relationships-and-eager-loading.md) - Defining relationships
- [Querying](querying.md) - Query building and pagination
- [Events & Observers](events-and-observers.md) - Lifecycle hooks
