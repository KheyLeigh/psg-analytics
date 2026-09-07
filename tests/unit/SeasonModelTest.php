<?php
declare(strict_types=1);
// Vérifie que Season expose bien is_current depuis une ligne de base.
final class SeasonModelTest extends TestCase
{
    public function testFromRowExposeIsCurrent(): void
    {
        $courante = Season::fromRow(['id' => 1, 'label' => '2026-27', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => 1]);
        $ancienne = Season::fromRow(['id' => 2, 'label' => '2025-26', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_current' => 0]);

        $this->assertTrue($courante->isCurrent, 'is_current=1 -> true');
        $this->assertTrue($ancienne->isCurrent === false, 'is_current=0 -> false');
    }
}
