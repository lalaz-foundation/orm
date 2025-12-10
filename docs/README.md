# Lalaz ORM Documentation

A powerful ActiveRecord-style ORM for PHP applications built on top of `lalaz/database`.

## Quick Start

```php
use Lalaz\Orm\Model;
use Lalaz\Orm\ModelManager;

// Define a model
final class Post extends Model
{
    protected ?string $table = 'posts';
    protected array $fillable = ['title', 'body', 'published'];
    protected array $casts = ['published' => 'bool', 'published_at' => 'datetime'];
    
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}

// Create and query
$post = Post::create($orm, ['title' => 'Hello', 'body' => '...']);

$posts = Post::queryWith($orm)
    ->where('published', true)
    ->with('author')
    ->orderBy('published_at', 'desc')
    ->paginate(10);
```

## Documentation

### Getting Started

- [Getting Started](getting-started.md) - Installation and bootstrapping
- [Configuration](configuration.md) - Full configuration reference
- [Config Cheatsheet](config-cheatsheet.md) - Quick config reference

### Models

- [Models & Attributes](models.md) - Defining models, attributes, and casting
- [Querying & Pagination](querying.md) - Query building and pagination
- [Validation](validation.md) - Model validation integration

### Relationships

- [Relationships & Eager Loading](relationships-and-eager-loading.md) - Defining and loading relationships

### Advanced

- [Events & Observers](events-and-observers.md) - Lifecycle events and observers
- [Multi-Tenancy](multi-tenancy.md) - Tenant scope support
- [Testing & Factories](testing-and-factories.md) - Model factories for testing
- [Flows & Patterns](flows-and-patterns.md) - Common patterns and workflows
- [Examples](examples.md) - Practical code examples

### Reference

- [Model Reference](reference-model.md) - Complete Model API
- [Query Reference](reference-query.md) - ModelQuery API
- [Relations Reference](reference-relations.md) - Relationship API
- [Database Reference](reference-database.md) - Database integration
- [Connections & Observability](connections-and-observability.md) - Connection management

### Errors

- [Errors](errors.md) - Error handling guide
- [Exceptions Table](exceptions-table.md) - All exception types

## Features

| Feature | Description |
|---------|-------------|
| **ActiveRecord Pattern** | Intuitive model-based database access |
| **Relationships** | HasOne, HasMany, BelongsTo, BelongsToMany |
| **Eager Loading** | Prevent N+1 queries with `with()` |
| **Timestamps** | Automatic `created_at`/`updated_at` |
| **Soft Deletes** | Soft delete with `deleted_at` |
| **Attribute Casting** | int, float, bool, array, datetime, enums |
| **Mass Assignment** | Fillable/guarded protection |
| **Validation** | Integrated model validation |
| **Events & Observers** | Lifecycle hooks |
| **Global Scopes** | Automatic query constraints |
| **Multi-Tenancy** | Built-in tenant scope trait |
| **Optimistic Locking** | Conflict detection |
| **Lazy Loading Guard** | Prevent N+1 at runtime |
| **Pagination** | Built-in pagination support |
| **Model Factories** | Testing support |

## Requirements

- PHP 8.2+
- lalaz/database

## License

MIT License
