<?php

declare(strict_types=1);

namespace Lalaz\Orm\Traits;

trait HasTimestamps
{
    protected bool $timestamps = true;
    protected string $createdAtColumn = 'created_at';
    protected string $updatedAtColumn = 'updated_at';

    protected function touchTimestamps(): void
    {
        if (!$this->timestamps) {
            return;
        }

        $now = $this->now();
        $this->setAttribute(
            $this->updatedAtColumn,
            $this->formatDateTime($now),
        );

        if (!$this->exists) {
            $this->setAttribute(
                $this->createdAtColumn,
                $this->formatDateTime($now),
            );
        }
    }
}
