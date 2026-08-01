<?php
declare(strict_types=1);
// tests/unit/DatabaseTest.php
final class DatabaseTest extends TestCase
{
    public function testConnexionSqliteRetournePdo(): void
    {
        putenv('DB_DRIVER=sqlite');
        Database::reset();
        $pdo = Database::connection();
        $this->assertTrue($pdo instanceof PDO, 'instance PDO');
    }

    public function testForeignKeysActivees(): void
    {
        putenv('DB_DRIVER=sqlite');
        Database::reset();
        $pdo = Database::connection();
        $on = $pdo->query('PRAGMA foreign_keys')->fetchColumn();
        $this->assertSame('1', (string) $on, 'FK activées');
    }
}
