# Querying & Pagination

Use `Model::queryWith($manager)` or `$model->query()` to get a `ModelQuery`, which wraps the database query builder.

## Common operations

```php
$posts = Post::queryWith($orm)
    ->where('published', true)
    ->orderBy('published_at', 'desc')
    ->get();

$one = Post::queryWith($orm)->findOrFail($id);
$first = Post::queryWith($orm)->firstOrFail();
```

## Soft deletes
- `withTrashed()` includes soft‑deleted rows.
- `onlyTrashed()` scopes to deleted rows.

## Batch operations
- `insertMany([['title' => 'a'], ['title' => 'b']])`
- `upsert($values, uniqueBy: 'slug', updateColumns: ['title'])`
- `updateWhere(['status' => 'draft'], ['status' => 'published'])`
- `deleteWhere(['archived' => true])`

## Pagination & chunking
- `paginate($perPage = 15, $page = 1)` returns array payload with `data`, `total`, `per_page`, `current_page`, `last_page`, `from`, `to`.
- `chunk($size, fn(array $models, int $page) => ...)` processes batches; return `false` to stop.
- `each($callback, $size = 100)` iterates model by model.

## Scopes
- Local scopes: define `scopeActive(ModelQuery $query)` and call `$model->query()->active()`.
- Global scopes: `Model::addGlobalScope('name', fn($builder, $model) => ...)`. `withoutGlobalScopes()` skips them; `withoutGlobalScopes(['foo'])` keeps selected ones.

## Concurrency
- `lockForUpdate()` / `sharedLock()` for pessimistic locking.
- Optimistic locking via `$usesOptimisticLocking = true` and `$lockColumn` (see models).

## Transactions
- `$manager->transaction(fn() => ...)` wraps operations in a DB transaction.

## Read/write routing
- All queries use the injected `Connection`, which may route reads to replicas (sticky or non‑sticky) based on `config/database.php`. Writes and transactions always hit the primary.
