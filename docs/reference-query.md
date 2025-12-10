# Query & Pagination Reference

`ModelQuery` wraps the database query builder with model awareness (hydration, relations, scopes).

## Creation
- `$model->query()`: `ModelQuery` scoped to the instance’s connection/table.
- `Model::queryWith(ModelManager $mgr)`: static entry point.
- `$model->withTrashed()`, `$model->onlyTrashed()`, `$model->withoutGlobalScopes()`.

## Retrieval
- `get(): array<Model>` – hydrates and eager loads.
- `first(): ?Model`
- `firstOrFail(): Model` – `ModelNotFoundException`.
- `find($id): ?Model`
- `findOrFail($id): Model`

## Filtering & scopes
- Proxy to underlying builder: `where`, `orWhere`, `whereIn`, `whereNull`, `orderBy`, `groupBy`, `having`, `limit`, `offset`, `forPage`, `lock`, etc.
- Local scopes: `scopeX` methods on model are callable as `$query->x(...)`.
- Global scopes applied unless `withoutGlobalScopes()` (or with allowlist).

## Eager loading
- `with(array|string $relations): self`
  - Accepts `['comments' => fn($q) => ...]` for constraints.
  - Throws `RelationNotFoundException` if missing; `InvalidRelationException` if not returning a Relation.
  - Batches per relation to avoid N+1.

## Batch operations
- `insertMany(array $rows): bool`
- `upsert(array $values, array|string $uniqueBy, ?array $updateColumns = null): bool`
- `updateWhere(array $conditions, array $values): int`
- `deleteWhere(array $conditions): int`

## Locks & concurrency
- `lockForUpdate(): self` – adds `FOR UPDATE` / `FOR UPDATE`-like clause.
- `sharedLock(): self` – adds shared/`LOCK IN SHARE MODE`/`FOR SHARE`.
- Optimistic locking handled in `Model::save()` when enabled.

## Pagination & iteration
- `paginate(int $perPage = 15, int $page = 1): array`
  - Returns: `data`, `total`, `per_page`, `current_page`, `last_page`, `from`, `to`.
- `chunk(int $count, callable $callback): void` – callback gets `(array $models, int $page)`; return false to stop.
- `each(callable $callback, int $count = 100): void` – iterates models; return false to stop.

## Soft deletes helpers
- `withTrashed()` includes deleted rows.
- `onlyTrashed()` filters to deleted rows.

## Errors thrown
- `ModelNotFoundException` (find/first or fail)
- `RelationNotFoundException`, `InvalidRelationException` (invalid eager loads)
