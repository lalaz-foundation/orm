# Model Reference

## Core properties
- `$table`: string|null – overrides inferred table.
- `$primaryKey`: string – default `id`.
- `$incrementing`: bool – default true; false triggers key generation for uuid/ulid/string.
- `$keyType`: `int|string|uuid|ulid|string` – default `int`.
- `$timestamps`: bool – from `HasTimestamps`; default true (configurable).
- `$softDeletes`: bool – from `HasSoftDeletes`; default false (configurable).
- `$createdAtColumn` / `$updatedAtColumn` / `$deletedAtColumn`: column names.
- `$useCamelAttributes`: bool – opt into camelCase attribute keys.
- `$dateFormat`: string – default `DATE_ATOM`.
- `$timezone`: string|null – default PHP timezone.
- `$fillable` / `$guarded`: arrays controlling mass assignment.
- `$visible` / `$hidden`: serialization.
- `$casts`: map of attribute => cast type (`int`, `float`, `bool`, `string`, `array|json`, `datetime|datetime_immutable|date|timestamp`, `enum:Class`, enum class).
- `$preventLazyLoading`, `$allowLazyLoadingInTesting`, `$lazyAllowed`: lazy guard controls.
- `$usesOptimisticLocking`, `$lockColumn`: optimistic locking.
- `$enforceFillable`, `$throwOnMassAssignment`: mass assignment behavior.

## Factory/creation helpers
- `static build(ModelManager $mgr, array $attrs = []): static` – instantiate + fill.
- `static create(ModelManager $mgr, array $attrs): static` – build + save.
- `static queryWith(ModelManager $mgr): ModelQuery` – start a query.
- `static all(ModelManager $mgr): array<static>` – fetch all.
- `static find(ModelManager $mgr, mixed $id): ?static`
- `static findOrFail(ModelManager $mgr, mixed $id): static` – throws `ModelNotFoundException`.

## Instance helpers
- `tableName(): string` – resolves `$table` or inferred.
- `getTable(): string`
- `getKeyName(): string`
- `getKey(): mixed`
- `getKeyType(): string`
- `newInstance(array $attrs = [], bool $exists = false): static` – clone with attrs.
- `newFromBuilder(array $attrs, bool $exists = true): static` – for hydration.
- `newQuery(bool $includeTrashed = false, bool $applyGlobalScopes = true): QueryBuilder`
- `query(): ModelQuery`
- `withTrashed(): ModelQuery`
- `onlyTrashed(): ModelQuery`
- `withoutGlobalScopes(): ModelQuery`

## Attributes & casting
- `fill(array $attrs, bool $force = false): void` – throws `MassAssignmentException` when enforced.
- `forceFill(array $attrs): void` – bypass fillable.
- `getAttribute(string $key): mixed`
- `setAttribute(string $key, mixed $value, bool $markDirty = true): void`
- `getAttributes(): array<string,mixed>`
- `getDirty(): array<string,mixed>` / `isDirty(?string $key = null): bool`
- Dirty baseline resets on successful save via `syncOriginal()`.
- Casting rules apply on `getAttribute` and serialization; storage uses `prepareForStorage`.

## Persistence
- `save(): bool`
  - Calls `touchTimestamps()`, validation hook (if enabled), `creating/updating/saving` events, optimistic lock check, then insert/update.
  - On insert: auto PK from PDO lastInsertId when incrementing.
  - On success: fires `saved` + `created|updated`.
  - Throws `OptimisticLockException` when locking fails.
- `delete(): bool`
  - Soft deletes if enabled; fires `deleting/deleted`.
- `forceDelete(): bool` – bypass soft deletes.
- `restore(): bool` – soft delete only; fires `restoring/restored`.
- `refresh(): void` – reload current row (incl. trashed).

## Serialization
- `toArray(): array` – applies casts, visible/hidden, includes loaded relations.
- `toJson(int $options = 0): string`

## Events & observers
- `fireEvent(string $event): bool` – returns false to cancel.
- `protected function observers(): array` – list of observer classes/instances.
- `protected function registerObservers(): void` – auto-registered in constructor.

## Validation hook
- `protected function validationRules(string $operation): array` – per `create|update`.
- `protected function validationData(): array` – payload passed to validator.
- Calls bound `ModelValidatorInterface::validate()` when `validation.enabled` is true and rules not empty; throw to abort.

## Global scopes
- `static getGlobalScopes(): array<string, callable>`
- `static addGlobalScope(string $name, callable $scope): void`
- `static withoutGlobalScope(string $name): void`
- Applied on `newQuery()` unless disabled.

## Key generation
- `protected function newKey(): mixed` – uuid/ulid/string generation; throws `InvalidKeyException` if missing.

## Relation access
- `__get()` routes to relation when a method with the name exists.
- Lazy loading guard can throw `LazyLoadingViolationException` or `RelationNotFoundException`/`InvalidRelationException`.
