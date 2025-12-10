# Validation Hook (optional)

Validation is opt‑in and only runs when a `ModelValidatorInterface` is bound. The default `NullModelValidator` is a no‑op.

## Enable
- Set `validation.enabled` to `true` (default).
- Bind `ModelValidatorInterface` in the container or pass an implementation to `ModelManager`.

```php
use Lalaz\Orm\Contracts\ModelValidatorInterface;
use Lalaz\Orm\Validation\NullModelValidator;

$orm = new ModelManager($connection, $manager, $config, new NullModelValidator());
```

Replace `NullModelValidator` with your adapter to a validation package. Implement:

```php
public function validate(Model $model, array $data, array $rules, string $operation): void;
```

Throw `ValidationException` (or your own) to block persistence.

## Defining rules

```php
class Post extends Model
{
    protected function validationRules(string $operation): array
    {
        return match ($operation) {
            'create' => ['title' => 'required', 'body' => 'required'],
            'update' => ['title' => 'required'],
            default  => [],
        };
    }

    protected function validationData(): array
    {
        return $this->getAttributes(); // override to customize payload
    }
}
```

`save()` calls `validationRules()` before inserts/updates when enabled.
