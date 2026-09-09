<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/migrate.php';

// Vérifie que run_migration() traite chaque saison déclarée et renvoie un
// rapport indexé par clé de saison, sans mélanger les identifiants entre saisons.
final class MigratorLoopTest extends TestCase
{
    private function migratedPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);
        return $pdo;
    }

    public function testRunMigrationRenvoieUnRapportParCleDeSaison(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $reports = run_migration($pdo);

        $this->assertTrue(array_key_exists('2025-26', $reports), 'la clé 2025-26 est présente');
        $this->assertSame(55, $reports['2025-26']['matches']);
        $this->assertSame(24, $reports['2025-26']['players']);
    }

    public function testChaqueJoueurAUnSeasonIdCoherentAvecSaSaison(): void
    {
        $pdo = $this->migratedPdo();
        $n = (int) $pdo->query(
            'SELECT COUNT(*) FROM players p JOIN seasons s ON s.id = p.season_id WHERE s.label = "2025-26"'
        )->fetchColumn();
        $this->assertSame(24, $n, 'les 24 joueurs sont bien rattachés à la saison 2025-26');
    }

    public function testDeuxiemeSaisonCoexisteSansAlererLaPremiere(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $reports = run_migration($pdo);

        $this->assertTrue(array_key_exists('2026-27', $reports), 'la clé 2026-27 est présente');
        $this->assertSame(5, $reports['2026-27']['matches'], 'matchs 2026-27 déjà collectés par la tâche planifiée');
        $this->assertSame(24, $reports['2026-27']['players'], 'effectif professionnel 2026-27 déjà renseigné');

        // 2025-26 reste strictement identique, quelle que soit la présence de 2026-27.
        $this->assertSame(55, $reports['2025-26']['matches']);
        $this->assertSame(24, $reports['2025-26']['players']);
    }

    public function testUneSeuleSaisonEstCourante(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);

        $n = (int) $pdo->query('SELECT COUNT(*) FROM seasons WHERE is_current = 1')->fetchColumn();
        $this->assertSame(1, $n, 'exactement une saison courante');

        $label = $pdo->query('SELECT label FROM seasons WHERE is_current = 1')->fetchColumn();
        $this->assertSame('2026-27', $label, '2026-27 est la saison courante');
    }

    // I4 : la saison 2025-26 n'a pas de clé de source dédiée (fbref_2025-26) dans
    // sources.php ; ses matchs doivent donc rester attribués à la source partagée
    // fbref, même après l'ajout des clés dédiées 2026-27. Non-régression.
    public function testLesMatchs2025_26RestentAttribuesALaSourceFbrefPartagee(): void
    {
        $pdo = $this->migratedPdo();
        $label = $pdo->query(
            "SELECT d.label FROM matches m JOIN data_sources d ON d.id = m.source_id
             JOIN seasons s ON s.id = m.season_id WHERE s.label = '2025-26' LIMIT 1"
        )->fetchColumn();
        $this->assertSame('FBref : journal des matchs PSG 2025-26 (toutes compétitions)', $label);
    }
}
