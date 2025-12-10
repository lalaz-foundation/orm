# Relations & Pivot Reference

## Relation builders (available on Model)
- `hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne`
- `hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany`
- `belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo`
- `belongsToMany(string $related, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $parentKey = null, ?string $relatedKey = null): BelongsToMany`

## BelongsToMany pivot helpers
- `attach(array|int|string $ids, array $attributes = []): void`
- `detach(array|int|string|null $ids = null): int`
- `sync(array $ids, bool $detaching = true): void`
- `toggle(array|int|string $ids): void`
- `withPivot(string ...$columns): self` – include extra pivot columns when hydrating.

## Eager loading behavior
- Eager loads run per relation, batching keys across parents; constraints apply per relation.
- BelongsTo/HasOne map single related; HasMany/BelongsToMany group arrays keyed by local/parent key.
- Invalid relation names throw `RelationNotFoundException`; non‑Relation return throws `InvalidRelationException`.

## Lazy loading guard
- If enabled, accessing an unloaded relation throws `LazyLoadingViolationException` unless whitelisted.
- Missing relation method throws `RelationNotFoundException`.

## Relation cache
- Relations are cached on the model instance after first load; `forgetRelationCache(?string $name = null)` clears.
