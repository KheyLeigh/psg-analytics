<?php
declare(strict_types=1);
// tests/unit/ValidatorTest.php
final class ValidatorTest extends TestCase
{
    public function testIntBorne(): void
    {
        $this->assertSame(20, Validator::int('999', 1, 20, 10));
        $this->assertSame(10, Validator::int('abc', 1, 20, 10));
        $this->assertSame(5, Validator::int('5', 1, 20, 10));
    }

    public function testInListRejetteHorsListe(): void
    {
        $allowed = ['goals', 'assists', 'minutes'];
        $this->assertSame('goals', Validator::inList('goals', $allowed, 'goals'));
        $this->assertSame('goals', Validator::inList('DROP TABLE', $allowed, 'goals'));
    }
}
