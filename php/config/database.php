<?php
declare(strict_types=1);
// php/config/database.php — SQLite par défaut pour lancement immédiat
function db_config(): array
{
    $driver = getenv('DB_DRIVER') ?: 'sqlite';
    return [
        'driver'      => $driver,
        'sqlite_path' => BASE_PATH . '/database/psg.sqlite',
        'host'        => getenv('DB_HOST') ?: '127.0.0.1',
        'name'        => getenv('DB_NAME') ?: 'psg_analytics',
        'user'        => getenv('DB_USER') ?: 'root',
        'pass'        => getenv('DB_PASS') ?: '',
    ];
}
