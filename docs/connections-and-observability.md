# Connections, Pooling & Observability

The ORM delegates all I/O to `lalaz/database`. Understanding those knobs helps you tune performance and visibility.

## Pooling & timeouts
- Configured in `config/database.php` under `pool`:
  - `pool.max` / `pool.min`: max/min PDOs held in the pool.
  - `pool.timeout_ms`: how long `ConnectionManager` waits before throwing “Connection pool exhausted.”
- `ConnectionManager::poolStatus()` returns `{total, pooled, max, min}` for monitoring.

## Read replicas
- `config/database.php` `read` section:
  - `enabled` (`bool`), `driver` (defaults to primary), `sticky` (`bool`), `pool.{min,max,timeout_ms}`, and an array of `connections`.
  - Reads route to replicas; writes/transactions always hit primary. If `sticky=true`, a write in the same request forces subsequent reads to primary to avoid replication lag.
- `ConnectionManager::readDriver()` exposes the replica driver in use.

## Retries
- `config/database.php` `retry` block (handled in `Connection`):
  - `attempts` (default 1), `delay_ms`, and `retry_on` (array of PDO error codes). Applies to select/insert/update/delete.

## Query logging & metrics
- Every query dispatches an event via `ConnectionManager::dispatchQueryEvent`:
  - Payload: `type` (`select|insert|update|delete|statement`), `role` (`read|write`), `driver`, `sql`, `bindings`, `duration_ms`.
- Register listeners with `ConnectionManager::listenQuery(callable $listener)` to feed APM/metrics or custom logs.
- If a PSR `LoggerInterface` is bound in the container, `DatabaseServiceProvider` injects it and the manager will emit debug logs automatically:
  - Format: `[db:{driver}][{role}] {duration}ms {type} {sql}`, with `bindings` in the context.

## Observability tips
- Attach a listener to push query events to your metrics system (histograms on `duration_ms`, counters by `type`/`role`/`driver`).
- Enable sticky reads when using replicas to avoid stale reads after writes.
- Tune `pool.max` and `pool.timeout_ms` to match workload; low timeouts surface saturation quickly.

## ORM interaction
- `ModelManager` reuses the provided `Connection`/`ConnectionManager`; routing and logging happen transparently to `ModelQuery`/relations/eager loading.
- Locks (`lockForUpdate`/`sharedLock`) and optimistic locking work regardless of replicas (because writes go primary).
