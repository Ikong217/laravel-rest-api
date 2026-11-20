<?php
namespace IkongDev\RestApi\Helper;

class Header
{
    private array $headers = [];

    public function __construct(array $headers = [])
    {
        // auto-load all headers from the current request
        $this->headers = request()?->headers->all() ?? [];
        // manual overrides/additions win over the raw headers
        $this->headers = array_merge($this->headers, $headers);
    }

    public function __get($name)
    {
        $key   = strtolower(str_replace('_', '-', $name));
        $value = $this->headers[$key] ?? null;
        // Symfony HeaderBag stores each header as an array (multi-value support)
        return is_array($value) ? ($value[0] ?? null) : $value;
    }

    public function __set($name, $value)
    {
        $key                 = strtolower(str_replace('_', '-', $name));
        $this->headers[$key] = $value;
    }

    public function __isset($name)
    {
        $key = strtolower(str_replace('_', '-', $name));
        return isset($this->headers[$key]);
    }

    public function bearerToken(): ?string
    {
        $auth = $this->authorization;
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function all(): array
    {
        return $this->headers;
    }
}
