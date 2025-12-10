# Database/Connection Reference (used by ORM)

## ConnectionManager
- `__construct(array $config, ?LoggerInterface $logger = null, array $connectors = [])`
- `acquire(): PDO` – primary/write connection.
- `acquireRead(): PDO` – read replica when configured, otherwise primary.
- `release(PDO $pdo): void` / `releaseRead(PDO $pdo): void`
- `driver(): string` / `readDriver(): string`
- `config(string $key, mixed $default = null): mixed`
- `poolStatus(): array{total:int, pooled:int, max:int, min:int}`
- `listenQuery(callable $listener): void` – `callable(array $event): void`
- `dispatchQueryEvent(array $event): void`

### Config keys (database.php)
- `driver`: sqlite|mysql|postgres|custom.
- `pool.min|max|timeout_ms`.
- `read.enabled|driver|sticky|pool|min|max|timeout_ms|connections[]`.
- `retry.attempts|delay_ms|retry_on[]`.
- `connections[driver]`: DSN details per driver.
- `connectors`: map driver => ConnectorInterface/class for custom drivers.

### Query event payload
- `type`: select|insert|update|delete|statement
- `role`: read|write
- `driver`: driver name
- `sql`: string
- `bindings`: array
- `duration_ms`: float

If a PSR logger is injected, events are logged at debug as `[db:{driver}][{role}] {duration}ms {type} {sql}` with `bindings` context.

## Connection
- Proxies to PDO with profiling, retry, and routing.
- `select(string $sql, array $bindings = []): array`
- `insert(string $sql, array $bindings = []): bool`
- `update(string $sql, array $bindings = []): int`
- `delete(string $sql, array $bindings = []): int`
- `query(string $sql, array $bindings = []): PDOStatement`
- `transaction(callable $callback): mixed`
- `table(string $table): QueryBuilder`
- `grammar(): Grammar`
- `getPdo(): PDO`
- Internal routing: reads may use `acquireRead()` unless sticky/transaction/write forces primary; writes always primary.

## QueryBuilder (high level)
- Fluent API for select/joins/where/group/having/order/limit/offset/forPage/locks.
- Batch: `insertMany`, `upsert`.
- Locking: `lock('for update'|'share')` from ORM via `lockForUpdate`/`sharedLock`.
- Returns arrays (no model hydration here).

## Grammar
- Driver-specific quoting/compilation (MySQL/Postgres/SQLite).
- Lock clauses: `FOR UPDATE`, `LOCK IN SHARE MODE`/`FOR SHARE` depending on driver.
