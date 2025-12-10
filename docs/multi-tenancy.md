# Multi‑Tenancy

Use `HasTenantScope` to apply a tenant filter globally.

```php
use Lalaz\Orm\Traits\HasTenantScope;

class Invoice extends Model
{
    use HasTenantScope;
}

Invoice::setTenantId('t-123');
$invoices = Invoice::queryWith($orm)->get(); // automatically adds WHERE tenant_id = 't-123'
```

## Customizing
- `setTenantColumn('account_id')` to change the column.
- `setTenantId(null)` disables the scope.

The scope is applied once per model class and is compatible with eager loading and soft deletes.
