<?php
declare(strict_types=1);
// Encapsule la requête HTTP entrante : méthode, chemin, query.
final class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return '/' . trim($path, '/') === '/' ? '/' : rtrim($path, '/');
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function segments(): array
    {
        return array_values(array_filter(explode('/', $this->path())));
    }
}
