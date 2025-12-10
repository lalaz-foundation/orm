# Flows & Patterns

## Save lifecycle (insert/update)
1) `fill()` sets attributes (mass assignment guard may throw).
2) `save()`:
   - `touchTimestamps()` updates `created_at/updated_at` when enabled.
   - Validation hook runs if `validation.enabled` and rules exist (throws to abort).
   - Fires `creating|updating` then `saving` (false cancels).
   - For inserts: generate PK if needed; insert payload (camel→snake mapping when enabled); backfill incrementing ID; mark `exists`.
   - For updates: apply optimistic lock check when enabled; update payload.
   - On success: sync dirty baseline; fire `saved` then `created|updated`.
3) Exceptions: `MassAssignmentException`, `ValidationException` (optional), `OptimisticLockException`, `LazyLoadingViolationException` (if relation accessed), `RelationNotFoundException`/`InvalidRelationException`.

## Eager loading flow
1) `with([...])` registers relations + optional constraints (validated early).
2) `get()/first()/find*()` fetch rows.
3) Per relation:
   - Collect keys from parents.
   - Run one query with `IN` of keys (respecting constraints).
   - Map results back per relation type (belongsTo/hasOne single, hasMany/belongsToMany arrays, with pivot hydration).
4) Cached on model instances; cleared via `forgetRelationCache`.

## Read replica routing
- Reads use `acquireRead()` when `config['read'].enabled`; writes/transactions force primary.
- `sticky=true` keeps reads on primary after a write in the same request to avoid replication lag.
- Query events include `role=read|write` and `driver`.

## Lazy-loading guard pattern
- Enable `lazy_loading.prevent=true` (or per model) to surface N+1.
- Whitelist via `lazy_loading.allowed_relations` or per model `$lazyAllowed`.
- `APP_ENV=testing` + `allow_testing=true` can relax guard in tests.

## Pivot sync pattern
- Use `sync([$id => ['extra' => '...']])` to upsert pivot rows.
- `toggle()` flips membership.
- `withPivot()` ensures extra columns are selected into `pivot` relation.

## Optimistic locking pattern
- Enable `$usesOptimisticLocking` and set `$lockColumn` (default `updated_at`).
- Before update, query includes current lock value; failure throws `OptimisticLockException`.
- When lock column is `updated_at`, it auto-refreshes timestamp to advance the version.

## Batch write pattern
- Use `insertMany` for bulk inserts, `upsert` for merge semantics, `updateWhere`/`deleteWhere` for scoped mutations to reduce round-trips.

## Pitfalls & guards
- **Mass assignment**: define `$fillable` or set `enforce_fillable=false` if you want free-form; violations throw with table/fillable/guarded context.
- **N+1**: keep `lazy_loading.prevent` on in prod; eager load explicitly.
- **Soft deletes**: remember `withTrashed()` when fetching relations that may be deleted.
- **Camel mapping**: when `useCamelAttributes`/`naming.hydrate=camel`, persistence maps attribute names to snake columns. Be consistent in migrations/schema.
- **Replication lag**: prefer `sticky=true` when replicas are async; or force reads via primary for critical flows.
- **Transactions & read routing**: any active transaction forces reads to primary even with replicas.
