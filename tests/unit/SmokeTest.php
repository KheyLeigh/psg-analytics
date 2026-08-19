<?php
declare(strict_types=1);
// Test de fumée : confirme que le runner exécute bien un cas.
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
