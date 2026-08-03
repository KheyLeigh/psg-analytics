<?php
declare(strict_types=1);
// Vérifie la conservation du total et le déterminisme du tirage pondéré.
final class StatGeneratorTest extends TestCase
{
    public function testDistributionConserveLeTotal(): void
    {
        $gen = new StatGenerator(2026);
        $repartition = $gen->distributeByWeight(50, [29 => 11, 10 => 10, 7 => 8, 14 => 7, 9 => 6, 33 => 3]);
        $this->assertSame(50, array_sum($repartition), 'somme conservée');
    }

    public function testDeterministe(): void
    {
        $a = (new StatGenerator(2026))->distributeByWeight(50, [1 => 5, 2 => 3, 3 => 2]);
        $b = (new StatGenerator(2026))->distributeByWeight(50, [1 => 5, 2 => 3, 3 => 2]);
        $this->assertSame($a, $b, 'reproductible');
    }
}
