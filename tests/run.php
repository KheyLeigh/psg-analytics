<?php
declare(strict_types=1);
// tests/run.php
require __DIR__ . '/TestCase.php';

require dirname(__DIR__) . '/php/config/config.php';
require dirname(__DIR__) . '/php/config/database.php';
spl_autoload_register(function (string $class): void {
    foreach (['core', 'models', 'repositories', 'services'] as $dir) {
        $path = BASE_PATH . "/php/{$dir}/{$class}.php";
        if (is_file($path)) { require $path; return; }
    }
    $seedPath = BASE_PATH . "/database/seeds/{$class}.php";
    if (is_file($seedPath)) { require $seedPath; return; }
});

$files = glob(__DIR__ . '/unit/*Test.php') ?: [];
$failures = [];
$assertions = 0;
$count = 0;

foreach ($files as $file) {
    require $file;
    $class = basename($file, '.php');
    /** @var TestCase $instance */
    $instance = new $class();
    foreach (get_class_methods($instance) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $count++;
        try {
            $instance->$method();
            echo '.';
        } catch (Throwable $e) {
            echo 'F';
            $failures[] = "{$class}::{$method} — {$e->getMessage()}";
        }
    }
    $assertions += $instance->assertions();
}

echo "\n\n{$count} tests, {$assertions} assertions\n";
if ($failures !== []) {
    echo "\nÉCHECS:\n" . implode("\n", $failures) . "\n";
    exit(1);
}
echo "OK\n";
