<?php

declare(strict_types=1);

namespace Lalaz\Orm\Traits;

use Lalaz\Orm\Model;

/**
 * Adds a global scope for multi-tenancy by filtering on a tenant column.
 */
trait HasTenantScope
{
    protected static bool $tenantScopeBooted = false;
    protected static string $tenantColumn = 'tenant_id';
    protected static ?string $tenantId = null;

    protected function bootTenantScope(): void
    {
        if (static::$tenantScopeBooted) {
            return;
        }

        static::$tenantScopeBooted = true;

        static::addGlobalScope('tenant', function ($builder, Model $model): void {
            if (static::$tenantId !== null) {
                $builder->where(static::$tenantColumn, static::$tenantId);
            }
        });
    }

    public static function setTenantId(?string $tenantId): void
    {
        static::$tenantId = $tenantId;
    }

    public static function tenantId(): ?string
    {
        return static::$tenantId;
    }

    public static function tenantColumn(): string
    {
        return static::$tenantColumn;
    }

    public static function setTenantColumn(string $column): void
    {
        static::$tenantColumn = $column;
    }
}
