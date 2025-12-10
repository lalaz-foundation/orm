# Events, Observers & Scopes

## Model events
Emitted around lifecycle operations:
- `creating`, `created`
- `updating`, `updated`
- `saving`, `saved`
- `deleting`, `deleted`
- `restoring`, `restored`

Return `false` from an event listener to cancel the operation.

## Registering listeners

```php
$dispatcher = $orm->dispatcher();
$dispatcher->listen('model.creating', fn($model) => /* ... */);
```

## Observers

```php
use Lalaz\Orm\Events\Observer;

final class PostObserver extends Observer {
    public function creating($model) { /* ... */ }
    public function updating($model) { /* ... */ }
}

class Post extends Model {
    protected function observers(): array {
        return [PostObserver::class];
    }
}
```

Observers are resolved once per model instance during construction. Each method that matches an event name is registered automatically.

## Global & local scopes
- **Global**: `Model::addGlobalScope('tenancy', fn($builder, $model) => $builder->where('tenant_id', 1));`
- **Local**: methods prefixed with `scope`, e.g., `scopePublished(ModelQuery $q)` called via `$model->query()->published()`.

Use `withoutGlobalScopes()` or `withoutGlobalScopes(['name'])` to opt out per query.
