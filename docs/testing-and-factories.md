# Testing & Factories

## Factories

`Lalaz\Orm\Testing\Factory` offers lightweight, framework‑free factories for tests.

```php
use Lalaz\Orm\Testing\Factory;

// Create a factory with a definition callable that returns a Model instance
$factory = Factory::new($orm, User::class, fn() => User::build($orm, [
    'name' => 'Test User',
    'email' => 'test@example.com',
]));

// Apply state mutations (callback receives the Model instance)
$factory = $factory->state(fn(User $model) => $model->email = 'dev@lalaz.dev');

$user = $factory->create();           // persists
$drafts = $factory->count(3)->build(); // in-memory instances
```

- `Factory::new(ModelManager $manager, string $modelClass, callable $definition)` - creates a factory. The definition callable must return a Model instance.
- `state(callable $mutator)` - stacks attribute mutations. The callback receives the Model instance and returns void.
- `count(int $n)` - sets quantity of models to create.
- `build()` - returns models without saving; `create()` persists them.

## Integration setup

Integration tests can point to real MySQL/Postgres via the integration helpers. If Docker is unavailable, integration suites skip gracefully; unit tests run against in‑memory SQLite.

## Event/testing tips
- Use `APP_ENV=testing` with `lazy_loading.allow_testing=true` to permit lazy loading during tests.
- Factories play well with observers/events; use states to satisfy required attributes for validation.
