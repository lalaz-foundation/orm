# Configuration

All defaults live in `config/orm.php`. You can override per environment or per model (properties take precedence).

## Core options

- `timestamps.enabled` (`bool`): enable `created_at`/`updated_at`.
- `timestamps.created_at` / `timestamps.updated_at`: column names.
- `soft_deletes.enabled` (`bool`): use soft deletes with `deleted_at`.
- `soft_deletes.deleted_at`: column name.
- `enforce_fillable` (`bool`): when true, only `$fillable` may be mass‑assigned (unless guarded is overridden).
- `mass_assignment.throw_on_violation` (`bool`): throw `MassAssignmentException` instead of silently skipping.
- `dates.timezone`: default timezone for casting/formatting datetimes.
- `dates.format`: format used for datetime casts and timestamps.
- `lazy_loading.prevent` (`bool`): throw `LazyLoadingViolationException` when accessing unloaded relations.
- `lazy_loading.allow_testing` (`bool`): allow lazy loading in `APP_ENV=testing`.
- `lazy_loading.allowed_relations` (`string[]`): whitelist that can lazy load even when prevented.
- `validation.enabled` (`bool`): run validation when a validator is bound (see `validation.md`).
- `naming.hydrate`: `null` (use DB column names) or `"camel"` to map `snake_case` columns to `camelCase` attributes on load/save.

## Per‑model overrides

- `$table`, `$primaryKey`, `$incrementing`, `$keyType` (`int|string|uuid|ulid`), `$dateFormat`, `$timezone`.
- `$timestamps`/`$softDeletes` flags (from traits).
- `$useCamelAttributes` to force camel mapping regardless of config.
- `$useConfigTimestamps` / `$useConfigSoftDeletes` to ignore global settings.
- `$enforceFillable`, `$throwOnMassAssignment`.
- `$preventLazyLoading`, `$allowLazyLoadingInTesting`, `$lazyAllowed`.
- `$usesOptimisticLocking`, `$lockColumn`.
- `$fillable`, `$guarded`, `$visible`, `$hidden`, `$casts`.

## Database tie‑ins

The ORM reads database settings through the injected `ConnectionManager`. Pooling, retries, and read‑replica routing are configured in `config/database.php` (see the database package docs).
