<?php
declare(strict_types=1);
// Connexion PDO unique, portable MySQL/SQLite en singleton.
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $cfg = db_config();
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if ($cfg['driver'] === 'sqlite') {
            self::$pdo = new PDO('sqlite:' . $cfg['sqlite_path'], null, null, $options);
            // SQLite désactive les clés étrangères par défaut : on les active explicitement.
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
        }
        return self::$pdo;
    }

    // Réinitialise le singleton (utilisé par les tests pour changer de driver).
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
