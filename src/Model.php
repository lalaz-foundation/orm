<?php

declare(strict_types=1);

namespace Lalaz\Orm;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Lalaz\Database\Contracts\ConnectionInterface;
use Lalaz\Database\Query\QueryBuilder;
use Lalaz\Orm\Events\EventDispatcher;
use Lalaz\Orm\Query\ModelQuery;
use Lalaz\Orm\Relations\Relation;
use Lalaz\Orm\Traits\HasAttributes;
use Lalaz\Orm\Traits\HasRelationships;
use Lalaz\Orm\Traits\HasSoftDeletes;
use Lalaz\Orm\Traits\HasTimestamps;

/**
 * Lightweight ActiveRecord-style base model built on the Lalaz query builder.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hi@lalaz.dev>
 */
abstract class Model
{
    use HasAttributes;
    use HasRelationships;
    use HasTimestamps;
    use HasSoftDeletes;

    protected ConnectionInterface $connection;
    protected ?EventDispatcher $events = null;
    /**
     * @var array<class-string, array<string, callable>>
     */
    protected static array $globalScopes = [];
    protected bool $useCamelAttributes = false;
    protected bool $useConfigTimestamps = true;
    protected bool $useConfigSoftDeletes = true;

    /**
     * The table associated with the model. Override or implement tableName().
     */
    protected ?string $table = null;

    /**
     * The primary key for the model.
     */
    protected string $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    protected bool $incrementing = true;

    /**
     * Key type: "int", "string", "uuid", "ulid".
     */
    protected string $keyType = 'int';

    /**
     * Indicates if the model exists in the database.
     */
    protected bool $exists = false;

    /**
     * Mass-assignment guard flag.
     */
    protected bool $enforceFillable = true;

    /**
     * When false, non-fillable attributes are silently skipped instead of throwing.
     */
    protected bool $throwOnMassAssignment = true;

    /**
     * Date format used for timestamps and date casts.
     */
    protected string $dateFormat = DATE_ATOM;

    /**
     * Preferred timezone for date casts and timestamps (null = PHP default).
     */
    protected ?string $timezone = null;

    /**
     * Prevent lazy loading when true.
     */
    protected bool $preventLazyLoading = false;
    protected bool $allowLazyLoadingInTesting = false;
    /**
     * @var array<int, string> Relations allowed to lazy load.
     */
    protected array $lazyAllowed = [];

    /**
     * Enable optimistic locking. When true, updates include a version check.
     */
    protected bool $usesOptimisticLocking = false;

    /**
     * Column used for optimistic locking. Defaults to "updated_at".
     */
    protected string $lockColumn = 'updated_at';

    public function __construct(protected ModelManager $manager)
    {
        $this->connection = $manager->connection();
        $config = $manager->config();
        $this->events = $manager->dispatcher();
        $this->registerObservers();

        $timestamps = $config['timestamps'] ?? [];
        if (
            $this->useConfigTimestamps &&
            array_key_exists('enabled', $timestamps)
        ) {
            $this->timestamps = (bool) $timestamps['enabled'];
        }

        if (
            $this->useConfigTimestamps &&
            array_key_exists('created_at', $timestamps) &&
            $this->createdAtColumn === 'created_at'
        ) {
            $this->createdAtColumn = $timestamps['created_at'];
        }

        if (
            $this->useConfigTimestamps &&
            array_key_exists('updated_at', $timestamps) &&
            $this->updatedAtColumn === 'updated_at'
        ) {
            $this->updatedAtColumn = $timestamps['updated_at'];
        }

        $softDeletes = $config['soft_deletes'] ?? [];
        if (
            $this->useConfigSoftDeletes &&
            array_key_exists('enabled', $softDeletes)
        ) {
            $this->softDeletes = (bool) $softDeletes['enabled'];
        }

        if (
            $this->useConfigSoftDeletes &&
            array_key_exists('deleted_at', $softDeletes) &&
            $this->deletedAtColumn === 'deleted_at'
        ) {
            $this->deletedAtColumn = $softDeletes['deleted_at'];
        }

        $this->enforceFillable = (bool) ($config['enforce_fillable'] ?? true);
        $this->throwOnMassAssignment =
            (bool) ($config['mass_assignment']['throw_on_violation'] ?? true);
        $this->preventLazyLoading =
            (bool) ($config['lazy_loading']['prevent'] ?? false);
        $this->allowLazyLoadingInTesting =
            (bool) ($config['lazy_loading']['allow_testing'] ?? false);
        $this->lazyAllowed = $config['lazy_loading']['allowed_relations'] ?? [];

        $dates = $config['dates'] ?? [];
        $this->dateFormat = $dates['format'] ?? $this->dateFormat;
        $this->timezone = $dates['timezone'] ?? $this->timezone;

        if (method_exists($this, 'bootTenantScope')) {
            $this->bootTenantScope();
        }

        $naming = $config['naming']['hydrate'] ?? null;
        if ($naming === 'camel') {
            $this->useCamelAttributes = true;
        }
    }

    /**
     * Create a new instance with attributes mass-assigned.
     *
     * @param array<string, mixed> $attributes
     */
    public static function build(
        ModelManager $manager,
        array $attributes = [],
    ): static {
        /** @var static $instance */
        $instance = new static($manager);
        $instance->fill($attributes);
        return $instance;
    }

    /**
     * Returns the table name for the model.
     */
    public function tableName(): string
    {
        return $this->table ?? static::inferTableName();
    }

    public function getTable(): string
    {
        return $this->tableName();
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute($this->primaryKey);
    }

    public function getKeyType(): string
    {
        return $this->keyType;
    }

    public function getManager(): ModelManager
    {
        return $this->manager;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Infer a table name from the class (very basic pluralization).
     */
    protected static function inferTableName(): string
    {
        $class = basename(str_replace('\\', '/', static::class));
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class) ?? '');
        return $snake . 's';
    }

    /**
     * Start a new query for the model.
     */
    public function newQuery(
        bool $includeTrashed = false,
        bool $applyGlobalScopes = true,
    ): QueryBuilder {
        $builder = $this->newBaseQuery();

        if ($this->usesSoftDeletes() && !$includeTrashed) {
            $builder->whereNull($this->deletedAtColumn);
        }

        if ($applyGlobalScopes) {
            $this->applyGlobalScopes($builder);
        }

        return $builder;
    }

    /**
     * Create a raw query builder without scopes.
     */
    public function newBaseQuery(): QueryBuilder
    {
        return $this->connection->table($this->tableName());
    }

    public function query(): ModelQuery
    {
        return new ModelQuery($this, $this->newQuery());
    }

    public function withTrashed(): ModelQuery
    {
        return new ModelQuery(
            $this,
            $this->newQuery(includeTrashed: true),
            includeTrashed: true,
        );
    }

    public function onlyTrashed(): ModelQuery
    {
        $builder = $this->newQuery(includeTrashed: true)->whereNotNull(
            $this->deletedAtColumn,
        );
        return new ModelQuery($this, $builder, includeTrashed: true);
    }

    /**
     * Create a query without global scopes.
     */
    public function withoutGlobalScopes(): ModelQuery
    {
        return new ModelQuery(
            $this,
            $this->newQuery(applyGlobalScopes: false),
            includeTrashed: false,
        );
    }

    public static function queryWith(ModelManager $manager): ModelQuery
    {
        return (new static($manager))->query();
    }

    /**
     * @return array<int, static>
     */
    public static function all(ModelManager $manager): array
    {
        return static::queryWith($manager)->get();
    }

    public static function find(ModelManager $manager, mixed $id): ?static
    {
        return static::queryWith($manager)->find($id);
    }

    public static function findOrFail(ModelManager $manager, mixed $id): static
    {
        return static::queryWith($manager)->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(
        ModelManager $manager,
        array $attributes,
    ): static {
        $instance = static::build($manager, $attributes);
        $instance->save();
        return $instance;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function newInstance(
        array $attributes = [],
        bool $exists = false,
    ): static {
        $model = new static($this->manager);
        $model->fill(
            $model->mapFromDatabaseAttributes($attributes),
            force: true,
        );
        $model->exists = $exists;
        if ($exists) {
            $model->syncOriginal();
        }

        return $model;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function newFromBuilder(
        array $attributes,
        bool $exists = true,
    ): static {
        return $this->newInstance($attributes, $exists);
    }

    /**
     * Validation rules for the model keyed by attribute.
     *
     * @return array<string, mixed>
     */
    protected function validationRules(string $operation): array
    {
        return [];
    }

    /**
     * Data exposed to the validator before persistence.
     *
     * @return array<string, mixed>
     */
    protected function validationData(): array
    {
        return $this->getAttributes();
    }

    private function runValidation(string $operation): void
    {
        $config = $this->manager->config()['validation'] ?? [];
        $enabled = (bool) ($config['enabled'] ?? false);

        if (!$enabled) {
            return;
        }

        $rules = $this->validationRules($operation);
        if ($rules === []) {
            return;
        }

        $this->manager
            ->validator()
            ->validate($this, $this->validationData(), $rules, $operation);
    }

    /**
     * Save the model to the database (insert or update).
     */
    public function save(): bool
    {
        $this->touchTimestamps();

        $this->runValidation($this->exists ? 'update' : 'create');

        $data = $this->getDirty();
        if (!$this->exists) {
            if ($this->events && $this->fireEvent('creating') === false) {
                return false;
            }

            if ($this->events && $this->fireEvent('saving') === false) {
                return false;
            }

            // Recompute dirty attributes after observers may have mutated state.
            $data = $this->getDirty();

            if (!$this->incrementing && $this->getKey() === null) {
                $this->setAttribute($this->primaryKey, $this->newKey());
                $data = $this->getDirty();
            }

            $payload = $this->transformForStorage($data);
            $result = $this->newQuery()->insert([$payload]);
            if ($result && $this->incrementing) {
                // Best effort: fetch last insert id from PDO if supported
                $id = $this->connection->getPdo()->lastInsertId();
                if ($id !== false && $id !== null && $id !== '') {
                    $this->setAttribute(
                        $this->primaryKey,
                        $id,
                        markDirty: false,
                    );
                }
            }
            $this->exists = true;
            $this->syncOriginal();

            if ($result && $this->events) {
                $this->fireEvent('saved');
                $this->fireEvent('created');
            }
            return $result;
        }

        if ($data === []) {
            return true;
        }

        if ($this->events && $this->fireEvent('updating') === false) {
            return false;
        }

        if ($this->events && $this->fireEvent('saving') === false) {
            return false;
        }

        $query = $this->usesSoftDeletes()
            ? $this->newQuery(includeTrashed: true)
            : $this->newQuery();

        if ($this->usesOptimisticLocking) {
            $currentVersion = $this->getAttribute($this->lockColumn);
            if ($currentVersion !== null) {
                $query->where($this->lockColumn, $currentVersion);
                // Increment lock column for next version (datetime keeps ISO string fresh)
                if ($this->lockColumn === $this->updatedAtColumn) {
                    $data[$this->lockColumn] = $this->formatDateTime(
                        $this->now(),
                    );
                } else {
                    $data[$this->lockColumn] =
                        is_numeric($currentVersion) || $currentVersion === null
                            ? ((int) $currentVersion) + 1
                            : $currentVersion;
                }
            } elseif ($this->lockColumn === $this->updatedAtColumn) {
                // Ensure first update from null sets a value to avoid null comparisons failing
                $query->whereNull($this->lockColumn);
                $data[$this->lockColumn] = $this->formatDateTime($this->now());
            }
        }

        $payload = $this->transformForStorage($data);

        $updated = $query
            ->where($this->primaryKey, $this->getAttribute($this->primaryKey))
            ->update($payload);

        if ($updated) {
            $this->syncOriginal();
            if ($this->events) {
                $this->fireEvent('saved');
                $this->fireEvent('updated');
            }
        } elseif ($this->usesOptimisticLocking) {
            throw \Lalaz\Orm\Exceptions\OptimisticLockException::forModel(
                static::class,
                $this->getAttribute($this->primaryKey),
            );
        }

        return $updated > 0;
    }

    /**
     * Delete the model (soft delete if enabled).
     */
    public function delete(): bool
    {
        if ($this->events && $this->fireEvent('deleting') === false) {
            return false;
        }

        if ($this->usesSoftDeletes()) {
            $this->markDeleted();
            $result = $this->save();
            if ($result && $this->events) {
                $this->fireEvent('deleted');
            }
            return $result;
        }

        $deleted = $this->newQuery()
            ->where($this->primaryKey, $this->getAttribute($this->primaryKey))
            ->delete();

        if ($deleted) {
            $this->exists = false;
            if ($this->events) {
                $this->fireEvent('deleted');
            }
        }

        return $deleted > 0;
    }

    /**
     * Force delete ignoring soft deletes.
     */
    public function forceDelete(): bool
    {
        $deleted = $this->newQuery(includeTrashed: true)
            ->where($this->primaryKey, $this->getAttribute($this->primaryKey))
            ->delete();

        if ($deleted) {
            $this->exists = false;
        }

        return $deleted > 0;
    }

    /**
     * Restore a soft-deleted model.
     */
    public function restore(): bool
    {
        if (!$this->usesSoftDeletes()) {
            return false;
        }

        $this->clearDeleted();
        if ($this->events && $this->fireEvent('restoring') === false) {
            return false;
        }

        $result = $this->save();
        if ($result && $this->events) {
            $this->fireEvent('restored');
        }

        return $result;
    }

    /**
     * Reload the model from the database.
     */
    public function refresh(): void
    {
        $fresh = $this->newQuery(includeTrashed: true)
            ->where($this->primaryKey, $this->getAttribute($this->primaryKey))
            ->first();

        if (is_array($fresh)) {
            $this->fill($fresh, force: true);
            $this->exists = true;
            $this->syncOriginal();
        }
    }

    /**
     * Lazy loads a relation by invoking the relationship method.
     */
    protected function getRelation(string $name): mixed
    {
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        if (!method_exists($this, $name)) {
            throw \Lalaz\Orm\Exceptions\RelationNotFoundException::forRelation(
                $name,
                static::class,
                $this->tableName(),
            );
        }

        $hasWhitelist = $this->lazyAllowed !== [];
        $isAllowed = $hasWhitelist && in_array($name, $this->lazyAllowed, true);
        $inTesting = getenv('APP_ENV') === 'testing';

        if (
            $this->preventLazyLoading &&
            !($inTesting && $this->allowLazyLoadingInTesting) &&
            !$isAllowed
        ) {
            throw \Lalaz\Orm\Exceptions\LazyLoadingViolationException::forRelation(
                $name,
                static::class,
            );
        }

        $relation = $this->{$name}();
        if (!$relation instanceof Relation) {
            throw \Lalaz\Orm\Exceptions\InvalidRelationException::forRelation(
                $name,
                static::class,
            );
        }

        $relation->as($name);
        $this->relations[$name] = $relation->getResults();
        return $this->relations[$name];
    }

    /**
     * @return array<string, callable>
     */
    public static function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }

    public static function addGlobalScope(string $name, callable $scope): void
    {
        static::$globalScopes[static::class][$name] = $scope;
    }

    public static function withoutGlobalScope(string $name): void
    {
        unset(static::$globalScopes[static::class][$name]);
    }

    protected function applyGlobalScopes(QueryBuilder $builder): void
    {
        foreach ($this->getGlobalScopes() as $scope) {
            $scope($builder, $this);
        }
    }

    protected function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->resolveTimezone());
    }

    protected function resolveTimezone(): DateTimeZone
    {
        $tz = $this->timezone ?? date_default_timezone_get();
        return new DateTimeZone($tz);
    }

    protected function formatDateTime(
        DateTimeInterface|string|int $value,
    ): string {
        $date =
            $value instanceof DateTimeInterface
                ? $value
                : new DateTimeImmutable(
                    is_int($value) || ctype_digit((string) $value)
                        ? '@' . $value
                        : (string) $value,
                    $this->resolveTimezone(),
                );

        if ($date instanceof DateTimeImmutable) {
            $date = $date->setTimezone($this->resolveTimezone());
        } else {
            $date->setTimezone($this->resolveTimezone());
        }

        return $date->format($this->dateFormat);
    }

    protected function fireEvent(string $event): bool
    {
        return $this->events?->dispatch('model.' . $event, $this) ?? true;
    }

    protected function registerObservers(): void
    {
        foreach ($this->observers() as $observer) {
            if (is_string($observer)) {
                if (!class_exists($observer)) {
                    continue;
                }
                $observer = new $observer();
            }
            foreach (
                [
                    'creating',
                    'created',
                    'updating',
                    'updated',
                    'saving',
                    'saved',
                    'deleting',
                    'deleted',
                    'restoring',
                    'restored',
                ] as $event
            ) {
                $this->events?->listen('model.' . $event, [$observer, $event]);
            }
        }
    }

    /**
     * @return array<int, object|string>
     */
    protected function observers(): array
    {
        return [];
    }

    /**
     * Map database column names to in-memory attribute keys.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function mapFromDatabaseAttributes(array $attributes): array
    {
        if (!$this->useCamelAttributes) {
            return $attributes;
        }

        $mapped = [];
        foreach ($attributes as $key => $value) {
            $mapped[$this->fromDatabaseKey($key)] = $value;
        }
        return $mapped;
    }

    /**
     * Convert attribute keys to database column names for persistence.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function transformForStorage(array $attributes): array
    {
        if (!$this->useCamelAttributes) {
            return $attributes;
        }

        $mapped = [];
        foreach ($attributes as $key => $value) {
            $mapped[$this->toDatabaseKey($key)] = $value;
        }
        return $mapped;
    }

    protected function fromDatabaseKey(string $key): string
    {
        if (!$this->useCamelAttributes) {
            return $key;
        }

        return lcfirst(str_replace('_', '', ucwords($key, '_')));
    }

    protected function toDatabaseKey(string $key): string
    {
        if (!$this->useCamelAttributes) {
            return $key;
        }

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key) ?? $key);
    }

    protected function newKey(): mixed
    {
        return match ($this->keyType) {
            'uuid' => $this->generateUuid(),
            'ulid' => $this->generateUlid(),
            'string' => bin2hex(random_bytes(16)),
            default
            => throw \Lalaz\Orm\Exceptions\InvalidKeyException::missingKey(
                static::class,
            ),
        };
    }

    protected function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    protected function generateUlid(): string
    {
        $time = (int) (microtime(true) * 1000);
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

        $timeChars = '';
        for ($i = 9; $i >= 0; $i--) {
            $mod = $time % 32;
            $timeChars = $alphabet[$mod] . $timeChars;
            $time = intdiv($time, 32);
        }

        $random = '';
        for ($i = 0; $i < 16; $i++) {
            $random .= $alphabet[random_int(0, 31)];
        }

        return $timeChars . $random;
    }
}
