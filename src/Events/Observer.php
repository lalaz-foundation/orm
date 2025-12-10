<?php

declare(strict_types=1);

namespace Lalaz\Orm\Events;

use Lalaz\Orm\Model;

/**
 * Base observer to handle model lifecycle events.
 */
abstract class Observer
{
    public function creating(Model $model): void
    {
    }

    public function created(Model $model): void
    {
    }

    public function updating(Model $model): void
    {
    }

    public function updated(Model $model): void
    {
    }

    public function saving(Model $model): void
    {
    }

    public function saved(Model $model): void
    {
    }

    public function deleting(Model $model): void
    {
    }

    public function deleted(Model $model): void
    {
    }

    public function restoring(Model $model): void
    {
    }

    public function restored(Model $model): void
    {
    }
}
