<?php
declare(strict_types=1);
// Vérifie le classement par métrique (liste blanche) sur les bilans saison
// vérifiés de 2025-26.
require_once dirname(__DIR__, 1) . '/../database/migrate.php';

final class RankingServiceTest extends TestCase
{
    private function migratedPdoEtSaison(): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);
        $seasonId = (int) $pdo->query("SELECT id FROM seasons WHERE label = '2025-26'")->fetchColumn();
        return [$pdo, $seasonId];
    }

    public function testClasseParButsVerifiesToutesCompetitions(): void
    {
        [$pdo, $seasonId] = $this->migratedPdoEtSaison();
        $svc = new RankingService(new PlayerSeasonStatsRepository($pdo), new PlayerRepository($pdo));

        $top = $svc->byMetric($seasonId, 'goals', 3);

        $this->assertCount(3, $top);
        $this->assertSame('Dembélé', $top[0]['player']->lastName, 'meilleur buteur vérifié toutes comps');
        $this->assertSame(20, $top[0]['value']);
        $this->assertTrue($top[0]['value'] >= $top[1]['value'], 'tri décroissant');
        $this->assertTrue($top[1]['value'] >= $top[2]['value'], 'tri décroissant');
    }

    public function testRejetteUneMetriqueHorsListeBlanche(): void
    {
        [$pdo, $seasonId] = $this->migratedPdoEtSaison();
        $svc = new RankingService(new PlayerSeasonStatsRepository($pdo), new PlayerRepository($pdo));

        $this->assertThrows(
            static fn () => $svc->byMetric($seasonId, 'DROP TABLE players; --', 5),
            InvalidArgumentException::class,
            'métrique non whitelistée rejetée'
        );
    }

    public function testClasseParContributionsButsPlusPasses(): void
    {
        [$pdo, $seasonId] = $this->migratedPdoEtSaison();
        $svc = new RankingService(new PlayerSeasonStatsRepository($pdo), new PlayerRepository($pdo));

        $top = $svc->byMetric($seasonId, 'goal_contributions', 1);

        $this->assertSame('Dembélé', $top[0]['player']->lastName, '20 buts + 11 passes = 31');
        $this->assertSame(31, $top[0]['value']);
    }
}
