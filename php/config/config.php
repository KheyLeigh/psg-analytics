<?php
declare(strict_types=1);
// Constantes globales : racine du projet, environnement et drapeau de debug.
define('BASE_PATH', dirname(__DIR__, 2));
define('ENV', getenv('APP_ENV') ?: 'local');
define('DEBUG', ENV === 'local');
