<?php
declare(strict_types=1);
// Socle de tests maison : assertions minimales, sans dépendance externe.
abstract class TestCase
{
    private int $ok = 0;

    public function assertTrue(bool $cond, string $msg = ''): void
    {
        if (!$cond) {
            throw new AssertionError("assertTrue échec: {$msg}");
        }
        $this->ok++;
    }

    public function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
    {
        if ($expected !== $actual) {
            $e = var_export($expected, true);
            $a = var_export($actual, true);
            throw new AssertionError("assertSame échec: {$msg} (attendu {$e}, obtenu {$a})");
        }
        $this->ok++;
    }

    public function assertCount(int $expected, array $arr, string $msg = ''): void
    {
        $this->assertSame($expected, count($arr), $msg);
    }

    public function assertThrows(callable $fn, string $class, string $msg = ''): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->assertTrue($e instanceof $class, "{$msg} (type reçu " . $e::class . ')');
            return;
        }
        throw new AssertionError("assertThrows échec: aucune exception ({$msg})");
    }

    public function assertions(): int
    {
        return $this->ok;
    }
}
