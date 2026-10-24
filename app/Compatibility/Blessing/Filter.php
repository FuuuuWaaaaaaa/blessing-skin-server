<?php

namespace Blessing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Filter
{
    protected array $listeners = [];

    public function __construct(protected Container $container)
    {
    }

    public function add(string $hook, $filter, $priority = 20): void
    {
        $this->listeners[$hook] ??= new Collection();
        $this->listeners[$hook]->push(['filter' => $filter, 'priority' => $priority]);
    }

    public function apply(string $hook, $initial, $arguments = [])
    {
        return $this->getListeners($hook)
            ->sortBy('priority')
            ->reduce(function ($carry, $listener) use ($arguments) {
                $filter = $listener['filter'];
                $callable = is_callable($filter)
                    ? $filter
                    : [$this->container->make($filter), 'filter'];

                return call_user_func_array($callable, array_merge([$carry], $arguments));
            }, $initial);
    }

    public function remove(string $hook): void
    {
        unset($this->listeners[$hook]);
    }

    public function getListeners(string $hook): Collection
    {
        return Arr::get($this->listeners, $hook, new Collection());
    }
}
