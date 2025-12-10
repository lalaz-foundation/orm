<?php

declare(strict_types=1);

namespace Lalaz\Orm\Events;

/**
 * Minimal event dispatcher for model lifecycle hooks.
 *
 * @package lalaz/orm
 * @author Gregory Serrao <hello@lalaz.dev>
 */
final class EventDispatcher
{
    /**
     * @var array<string, array<int, callable>>
     */
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Dispatch an event. If any listener returns false, propagation stops
     * and false is returned to signal cancellation.
     */
    public function dispatch(string $event, mixed ...$payload): bool
    {
        if (!isset($this->listeners[$event])) {
            return true;
        }

        foreach ($this->listeners[$event] as $listener) {
            $result = $listener(...$payload);
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    public function observe(string $event, object $observer): void
    {
        if (!method_exists($observer, $event)) {
            return;
        }

        $this->listen($event, [$observer, $event]);
    }
}
