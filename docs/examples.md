# End-to-End Examples

## CRUD with relations, validation, observers, optimistic lock, and lazy guard

```php
use Lalaz\Orm\Model;
use Lalaz\Orm\Contracts\ModelValidatorInterface;
use Lalaz\Orm\Events\Observer;

// Validator adapter (wrap your validation lib)
final class SimpleValidator implements ModelValidatorInterface
{
    public function validate(Model $model, array $data, array $rules, string $op): void
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            if (str_contains($rule, 'required') && empty($data[$field] ?? null)) {
                $errors[$field][] = 'required';
            }
        }
        if ($errors !== []) {
            throw \Lalaz\Orm\Exceptions\ValidationException::failed($errors);
        }
    }
}

// Observer
final class PostObserver extends Observer
{
    public function creating(Post $post): void
    {
        $post->slug = strtolower(str_replace(' ', '-', $post->title));
    }
}

final class User extends Model
{
    protected array $fillable = ['email', 'name'];
    public function posts() { return $this->hasMany(Post::class); }
}

final class Post extends Model
{
    protected array $fillable = ['user_id', 'title', 'body', 'published'];
    protected array $casts = ['published' => 'bool', 'published_at' => 'datetime'];
    protected bool $usesOptimisticLocking = true;
    protected bool $preventLazyLoading = true;

    public function author() { return $this->belongsTo(User::class, 'user_id'); }

    protected function observers(): array { return [PostObserver::class]; }
    protected function validationRules(string $op): array
    {
        return [
            'create' => ['title' => 'required', 'body' => 'required'],
            'update' => ['title' => 'required'],
        ][$op] ?? [];
    }
}

// Bootstrap
$orm = new \Lalaz\Orm\ModelManager($connection, $manager, $config, new SimpleValidator(), new \Lalaz\Orm\Events\EventDispatcher());

// Create (validation + observer runs, timestamps set)
$post = Post::create($orm, ['user_id' => 1, 'title' => 'Hello', 'body' => '...']);

// Read with eager loading (avoids lazy loading violation)
$post = Post::queryWith($orm)
    ->with('author')
    ->findOrFail($post->id);

// Update (optimistic lock: updated_at compared)
$post->title = 'Hello v2';
$post->save(); // throws OptimisticLockException if concurrent update won

// Lazy loading guard example (will throw because preventLazyLoading=true)
// $post->author; // unless eager loaded or whitelisted

// Soft delete + restore (if soft deletes enabled)
// $post->delete(); $post->restore();
```

## Pivot sync and eager constraints

```php
class Role extends Model {
    public function permissions() {
        return $this->belongsToMany(Permission::class)->withPivot('weight');
    }
}

$role = Role::findOrFail($orm, 1);

// Sync pivot with extra attributes
$role->permissions()->sync([
    10 => ['weight' => 5],
    11 => ['weight' => 3],
]);

// Eager load with constraint
$roles = Role::queryWith($orm)
    ->with(['permissions' => fn($q) => $q->where('weight', '>', 1)])
    ->get();
```

## Batch writes + transactions + read replicas

```php
// Bulk insert to reduce round-trips
Post::queryWith($orm)->insertMany([
    ['user_id' => 1, 'title' => 'A'],
    ['user_id' => 1, 'title' => 'B'],
]);

// Upsert on slug
Post::queryWith($orm)->upsert(
    [['slug' => 'hello', 'title' => 'Hello']],
    uniqueBy: 'slug',
    updateColumns: ['title']
);

// Transaction with read-your-writes (sticky replicas)
$orm->transaction(function () use ($orm) {
    $user = User::create($orm, ['email' => 'x@y.com']);
    // subsequent reads stay on primary when sticky=true
    $check = User::queryWith($orm)->findOrFail($user->id);
});
```
