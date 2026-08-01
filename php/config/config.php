<?php
declare(strict_types=1);
// php/config/config.php
define('BASE_PATH', dirname(__DIR__, 2));
define('ENV', getenv('APP_ENV') ?: 'local');
define('DEBUG', ENV === 'local');
