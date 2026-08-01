<?php
declare(strict_types=1);
// tests/unit/StatGeneratorTest.php
final class StatGeneratorTest extends TestCase
{
    public function testDistributionConserveLeTotal(): void
    {
        $gen = new StatGenerator(2026);
        $repartition = $gen->distributeGoals(74, [29 => 11, 10 => 10, 7 => 8, 14 => 7, 9 => 6, 33 => 3]);
        $this->assertSame(74, array_sum($repartition), 'somme conservée');
    }

    public function testDeterministe(): void
    {
        $a = (new StatGenerator(2026))->distributeGoals(50, [1 => 5, 2 => 3, 3 => 2]);
        $b = (new StatGenerator(2026))->distributeGoals(50, [1 => 5, 2 => 3, 3 => 2]);
        $this->assertSame($a, $b, 'reproductible');
    }

    public function testRespecteLesAncrages(): void
    {
        // les buteurs connus doivent au moins recevoir leur total vérifié
        $gen = new StatGenerator(2026);
        $r = $gen->distributeGoals(74, [29 => 11, 10 => 10, 7 => 8, 14 => 7, 9 => 6]);
        $this->assertTrue($r[29] >= 11, 'Barcola >= 11');
        $this->assertTrue($r[10] >= 10, 'Dembélé >= 10');
    }
}
