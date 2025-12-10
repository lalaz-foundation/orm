# Config Cheatsheet (Defaults vs Overrides)

## orm.php defaults
- `timestamps.enabled`: true
- `timestamps.created_at`: `created_at`
- `timestamps.updated_at`: `updated_at`
- `soft_deletes.enabled`: false
- `soft_deletes.deleted_at`: `deleted_at`
- `enforce_fillable`: true
- `mass_assignment.throw_on_violation`: true
- `dates.timezone`: null (PHP default)
- `dates.format`: `DATE_ATOM`
- `lazy_loading.prevent`: false
- `lazy_loading.allow_testing`: false
- `lazy_loading.allowed_relations`: []
- `validation.enabled`: true
- `naming.hydrate`: null (use DB column names)

## Common overrides by environment

**Local/Dev**
- `lazy_loading.allow_testing`: true
- `validation.enabled`: true
- `enforce_fillable`: true (catch mistakes early)
- `naming.hydrate`: `camel` if you want camelCase attributes

**Test**
- `lazy_loading.allow_testing`: true
- `lazy_loading.prevent`: true (surface N+1 in tests)
- `validation.enabled`: true
- `timestamps.enabled`: true
- `soft_deletes.enabled`: true (match prod behavior)

**Production**
- `lazy_loading.prevent`: true
- `lazy_loading.allow_testing`: false
- `validation.enabled`: true (with real validator bound)
- `enforce_fillable`: true
- `mass_assignment.throw_on_violation`: true
- `naming.hydrate`: null or `camel` depending on migration conventions

## database.php highlights
- `driver`: sqlite|mysql|postgres
- `pool.{min,max,timeout_ms}`: tune per workload
- `read.enabled|sticky|pool.*|connections[]`: enable replicas, prefer `sticky=true` to avoid stale reads
- `retry.{attempts,delay_ms,retry_on[]}`: enable idempotent retries for transient errors

## Model-level overrides
- `$timestamps`, `$softDeletes`, `$createdAtColumn`, `$updatedAtColumn`, `$deletedAtColumn`
- `$useCamelAttributes`, `$dateFormat`, `$timezone`
- `$preventLazyLoading`, `$allowLazyLoadingInTesting`, `$lazyAllowed`
- `$enforceFillable`, `$throwOnMassAssignment`
- `$usesOptimisticLocking`, `$lockColumn`
