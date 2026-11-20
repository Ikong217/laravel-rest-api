<?php
namespace App\Facades\Helper;

class Parameter
{
    private array $parameters = [];

    public function __construct(array $params = [])
    {
        // auto-load ?query=params from the current request
        $this->parameters = request()?->query() ?? [];
        // manual overrides/additions win over the raw query string
        $this->parameters = array_merge($this->parameters, $params);
    }

    public function __set($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function __get($name)
    {
        return $this->parameters[$name] ?? null;
    }

    public function __isset($name)
    {
        return isset($this->parameters[$name]);
    }

    public function all(): array
    {
        return $this->parameters;
    }
}
