<?php

declare(strict_types=1);

namespace Lalaz\Orm\Traits;

trait HasSoftDeletes
{
    protected bool $softDeletes = false;
    protected string $deletedAtColumn = 'deleted_at';

    protected function usesSoftDeletes(): bool
    {
        return $this->softDeletes;
    }

    protected function markDeleted(): void
    {
        if ($this->usesSoftDeletes()) {
            $this->setAttribute(
                $this->deletedAtColumn,
                $this->formatDateTime($this->now()),
            );
        }
    }

    protected function clearDeleted(): void
    {
        if ($this->usesSoftDeletes()) {
            $this->setAttribute($this->deletedAtColumn, null);
        }
    }
}
