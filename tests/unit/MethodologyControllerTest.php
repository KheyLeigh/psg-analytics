<?php
declare(strict_types=1);

// Vérifie que la page Méthodologie assemble bien les sources et la couverture par
// table fournies par le repository, sans les recalculer ni les altérer.
final class MethodologyControllerTest extends TestCase
{
    private function sourcesDouble(): SourceRepository
    {
        return new class(new PDO('sqlite::memory:')) extends SourceRepository {
            public function sources(): array
            {
                return [
                    ['label' => 'FBref', 'confidence' => 'verified', 'url' => 'https://fbref.com', 'note' => 'Scores', 'collectedAt' => '2026-08-11'],
                    ['label' => 'StatGenerator', 'confidence' => 'estimated', 'url' => null, 'note' => 'Attribution', 'collectedAt' => '2026-07-20'],
                ];
            }
            public function coverageByTable(): array
            {
                return [
                    ['label' => 'Matchs', 'total' => 55, 'verified' => 55, 'pct' => 100],
                    ['label' => 'Statistiques par match', 'total' => 515, 'verified' => 0, 'pct' => 0],
                ];
            }
        };
    }

    public function testAssembleSourcesEtCouverture(): void
    {
        $data = (new MethodologyController($this->sourcesDouble()))->buildViewData();

        $this->assertSame('methodology', $data['page']);
        $this->assertSame(2, count($data['sources']));
        $this->assertSame('verified', $data['sources'][0]['confidence']);
        $this->assertSame('estimated', $data['sources'][1]['confidence']);

        $this->assertSame(100, $data['coverage'][0]['pct']);
        $this->assertSame(0, $data['coverage'][1]['pct']);
        $this->assertSame(515, $data['coverage'][1]['total']);
    }
}
