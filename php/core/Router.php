<?php
declare(strict_types=1);
// php/core/Router.php
// Exception levée quand aucune route ne correspond à la requête.
final class RouteNotFound extends RuntimeException {}

// Fait correspondre une paire méthode/chemin à un contrôleur et une action.
final class Router
{
    /** @param array<string, array{0:string,1:string}> $routes */
    public function __construct(private array $routes) {}

    public function match(string $method, string $path): array
    {
        $path = $path === '/' ? '/' : rtrim($path, '/');
        foreach ($this->routes as $pattern => $handler) {
            [$verb, $route] = explode(' ', $pattern, 2);
            if ($verb !== strtoupper($method)) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $route) . '$#';
            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return ['controller' => $handler[0], 'action' => $handler[1], 'params' => $params];
            }
        }
        throw new RouteNotFound("Aucune route pour {$method} {$path}");
    }
}
