<?php
declare(strict_types=1);
// index.php — front controller
require __DIR__ . '/php/config/config.php';
require __DIR__ . '/php/config/database.php';

// Sous le serveur intégré PHP, le routeur intercepte tout : on laisse passer
// les fichiers statiques existants (assets/) au lieu de les faire tomber en 404.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (is_file($file)) { return false; }
}

spl_autoload_register(function (string $class): void {
    foreach (['core', 'models', 'repositories', 'services', 'controllers', 'controllers/Api'] as $dir) {
        $path = BASE_PATH . "/php/{$dir}/{$class}.php";
        if (is_file($path)) { require $path; return; }
    }
});

$request = new Request();
$router = new Router(require BASE_PATH . '/php/config/routes.php');

try {
    $route = $router->match($request->method(), $request->path());
    $controller = new $route['controller']();
    $controller->{$route['action']}($request, $route['params']);
} catch (RouteNotFound) {
    Response::html(View::render('errors/404', [], 'main'), 404);
} catch (Throwable $e) {
    if (DEBUG) { throw $e; }
    Response::html(View::render('errors/500', [], 'main'), 500);
}
