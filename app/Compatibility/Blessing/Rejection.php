<?php

namespace Blessing;

use Illuminate\Support\Arr;

class Rejection
{
    public function __construct(protected string $reason, protected $data = [])
    {
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getData($key = null, $default = null)
    {
        return $key === null ? $this->data : Arr::get($this->data, $key, $default);
    }
}
