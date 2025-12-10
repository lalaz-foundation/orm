# Models & Attributes

## Table & keys
- Table name defaults to plural snake of the class (`BlogPost` → `blog_posts`). Override `$table` or `tableName()`.
- Primary key defaults to `id`. Override `$primaryKey`.
- `$incrementing = false` with `$keyType = 'uuid'|'ulid'|'string'` generates a key on insert when missing.

## Mass assignment
- `$fillable`/`$guarded` control mass assignment. With `enforce_fillable=true`, attributes not in `$fillable` throw `MassAssignmentException` (message includes model, table, fillable, guarded).
- `forceFill()` bypasses fillable checks.

## Casting & mutators
- Built‑in casts: `int`, `float`, `bool`, `string`, `array`/`json`, `datetime`/`datetime_immutable`/`date`/`timestamp`, enums (`enum:MyEnum` or enum class), custom mutators/accessors `getFooAttribute` / `setFooAttribute`.
- Datetime casts honor `$dateFormat` and `$timezone` (or config defaults).
- `$casts` apply to serialization and to persistence via `prepareForStorage`.

## Visibility & serialization
- `$hidden`/`$visible` affect `toArray()`/`toJson()`. Loaded relations are included unless hidden.
- `useCamelAttributes=true` maps DB `snake_case` columns to `camelCase` attributes in memory; persistence reverses the mapping. The attribute keys are preserved in `toArray()`/`toJson()`.

## Dirty tracking
- `getDirty()` returns changed attributes; `isDirty()` checks per key or whole model.
- `syncOriginal()` resets the dirty baseline (called after successful save).

## Timestamps & soft deletes
- Traits `HasTimestamps`/`HasSoftDeletes` handle `created_at`/`updated_at`/`deleted_at` automatically. Use `withTrashed()`/`onlyTrashed()` to include soft‑deleted rows; `restore()` reverses them; `forceDelete()` bypasses soft deletes.

## Optimistic locking
- Enable `$usesOptimisticLocking = true`; configure `$lockColumn` (default `updated_at`). Updates include a version check and throw `OptimisticLockException` on conflict.

## Key generation
- Non‑incrementing models call `newKey()` on insert to generate UUID/ULID/string keys automatically when the PK is null.
