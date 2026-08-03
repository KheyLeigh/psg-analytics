<?php
declare(strict_types=1);
// Vérifie le bilan saison : couverture des 21 joueurs de champ, valeurs
// vérifiées, provenance verified, et lecture via le repository.
require_once dirname(__DIR__, 1) . '/../database/migrate.php';

final class PlayerSeasonStatsTest extends TestCase
{
    private function migratedPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);
        return $pdo;
    }

    public function testCouvreLes21JoueursDeChamp(): void
    {
        $pdo = $this->migratedPdo();
        $n = (int) $pdo->query('SELECT COUNT(*) FROM player_season_stats')->fetchColumn();
        $this->assertSame(21, $n, '21 joueurs de champ (gardiens exclus)');
    }

    public function testProvenanceTouteVerifiee(): void
    {
        $pdo = $this->migratedPdo();
        $nonVerifie = (int) $pdo->query(
            "SELECT COUNT(*) FROM player_season_stats s JOIN data_sources d ON d.id = s.source_id WHERE d.confidence <> 'verified'"
        )->fetchColumn();
        $this->assertSame(0, $nonVerifie, 'toutes les lignes bilan saison sont verified');
    }

    public function testBilanBarcolaViaRepository(): void
    {
        $pdo = $this->migratedPdo();
        $id = (int) $pdo->query("SELECT id FROM players WHERE last_name = 'Barcola'")->fetchColumn();
        $repo = new PlayerSeasonStatsRepository($pdo);
        $bilan = $repo->forPlayer($id);
        $this->assertTrue($bilan instanceof PlayerSeasonStats, 'bilan présent');
        $this->assertSame(49, $bilan->appearances, 'apparitions');
        $this->assertSame(35, $bilan->starts, 'titularisations');
        $this->assertSame(13, $bilan->goals, 'buts toutes comps');
        $this->assertSame(6, $bilan->assists, 'passes décisives');
        $this->assertSame(19, $bilan->goalContributions(), 'buts + passes');
    }

    public function testGardienSansBilan(): void
    {
        $pdo = $this->migratedPdo();
        $id = (int) $pdo->query("SELECT id FROM players WHERE last_name = 'Chevalier'")->fetchColumn();
        $repo = new PlayerSeasonStatsRepository($pdo);
        $this->assertSame(null, $repo->forPlayer($id), 'un gardien n\'a pas de bilan saison');
    }
}
