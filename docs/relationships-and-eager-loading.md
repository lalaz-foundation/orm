# Relationships & Eager Loading

## Defining relations

```php
class Post extends Model {
    public function author()   { return $this->belongsTo(User::class); }
    public function comments() { return $this->hasMany(Comment::class); }
    public function category() { return $this->hasOne(Category::class, 'id', 'category_id'); }
    public function tags()     { return $this->belongsToMany(Tag::class)->withPivot('weight'); }
}
```

### BelongsToMany helpers
- `attach($ids, $attrs = [])`, `detach($ids = null)`, `sync($ids, $detaching = true)`, `toggle($ids)`.
- `withPivot(...$columns)` to include extra pivot fields on retrieval.

## Lazy loading guard
- If `lazy_loading.prevent` is true (or `$preventLazyLoading`), accessing an unloaded relation throws `LazyLoadingViolationException` unless whitelisted in `lazy_loading.allowed_relations`.
- `APP_ENV=testing` obeys `lazy_loading.allow_testing`; you can also whitelist specific relations per model via `$lazyAllowed`.

## Eager loading

```php
Post::queryWith($orm)
    ->with('author')
    ->with(['comments' => fn($q) => $q->where('approved', true)])
    ->get();
```

- Eager loading batches per relation to avoid N+1. Constraints are applied per relation.
- Invalid relation names throw `RelationNotFoundException`; non‑Relation returns throw `InvalidRelationException`.

## Relation caching
- Accessed relations are cached on the model instance. Use `forgetRelationCache()` (optionally with a name) to clear.

## Locks
- `lockForUpdate()` / `sharedLock()` are available on `ModelQuery` for pessimistic concurrency during relationship queries as well.
