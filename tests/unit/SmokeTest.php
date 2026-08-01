<?php
declare(strict_types=1);
// tests/unit/SmokeTest.php
final class SmokeTest extends TestCase
{
    public function testVraiEstVrai(): void
    {
        $this->assertTrue(true, 'le runner fonctionne');
    }

    public function testEgalite(): void
    {
        $this->assertSame(4, 2 + 2, 'addition');
    }
}
