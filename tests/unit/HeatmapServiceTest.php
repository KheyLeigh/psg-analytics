<?php
declare(strict_types=1);
// Vérifie la matrice buts par joueur et par mois (regroupement portable via SUBSTR).
require_once dirname(__DIR__, 1) . '/../database/migrate.php';

final class HeatmapServiceTest extends TestCase
{
    private function migratedPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);
        return $pdo;
    }

    public function testMatriceMoisTrieeEtSommeEgaleAuTotalDesButsIndividuels(): void
    {
        $pdo = $this->migratedPdo();
        $svc = new HeatmapService(new StatisticRepository($pdo), new PlayerRepository($pdo));

        $matrix = $svc->goalsByPlayerAndMonth();

        $this->assertTrue(count($matrix['months']) > 1, 'la saison s\'étale sur plusieurs mois');
        $sorted = $matrix['months'];
        sort($sorted);
        $this->assertSame($sorted, $matrix['months'], 'mois triés par ordre chronologique');

        $total = 0;
        foreach ($matrix['rows'] as $row) {
            $total += array_sum($row['cells']);
            foreach ($matrix['months'] as $month) {
                $this->assertTrue(array_key_exists($month, $row['cells']), 'chaque ligne couvre tous les mois (0 par défaut)');
            }
        }
        $totalReel = (int) $pdo->query('SELECT SUM(goals) FROM player_match_stats')->fetchColumn();
        $this->assertSame($totalReel, $total, 'la somme de la matrice égale le total des buts individuels');
    }
}
