<?php
declare(strict_types=1);
require_once dirname(__DIR__, 1) . '/../database/seeds/Migrator.php';

// Vérifie les deux garde-fous du Migrator, indépendamment d'une migration réelle :
// le contrôle générique (toujours actif) et les totaux figés (optionnels par saison).
final class MigratorGuardsTest extends TestCase
{
    private function pdoAvecUnMatch(int $teamGoals, int $individualGoals): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY, is_psg INT)');
        $pdo->exec('CREATE TABLE matches (id INTEGER PRIMARY KEY, season_id INT, competition_id INT, home_team_id INT, away_team_id INT, home_goals INT, away_goals INT)');
        $pdo->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT)');
        $pdo->exec('CREATE TABLE player_match_stats (id INTEGER PRIMARY KEY, player_id INT, match_id INT, goals INT)');
        $pdo->exec('INSERT INTO teams VALUES (1,1),(2,0)');
        $pdo->exec("INSERT INTO matches VALUES (1,10,100,1,2,{$teamGoals},0)");
        $pdo->exec('INSERT INTO players VALUES (1,10)');
        $pdo->exec("INSERT INTO player_match_stats VALUES (1,1,1,{$individualGoals})");
        return $pdo;
    }

    public function testGardeFouGeneriqueLaisseFairePasserUnCasCoherent(): void
    {
        $pdo = $this->pdoAvecUnMatch(3, 3);
        migrator_verify_generic($pdo, 10);
        $this->assertTrue(true, 'aucune exception levée');
    }

    public function testGardeFouGeneriqueEchoueSiButsIndividuelsDepassentEquipe(): void
    {
        $pdo = $this->pdoAvecUnMatch(2, 3);
        $this->assertThrows(
            static fn () => migrator_verify_generic($pdo, 10),
            RuntimeException::class,
            'buts individuels (3) > buts d\'équipe (2)'
        );
    }

    public function testGardeFouGeneriqueDetecteUnJoueurDuneAutreSaison(): void
    {
        $pdo = $this->pdoAvecUnMatch(3, 3);
        // Le joueur 1 appartient en réalité à la saison 10 (season_id juste inséré) ;
        // on le fait pointer sur une autre saison pour simuler la fuite.
        $pdo->exec('UPDATE players SET season_id = 99 WHERE id = 1');
        $this->assertThrows(
            static fn () => migrator_verify_generic($pdo, 10),
            RuntimeException::class,
            'joueur d\'une autre saison'
        );
    }

    public function testTotauxFigesAbsentsNeDeclenchentAucuneVerification(): void
    {
        // Aucun fichier season_totals.php pour cette clé : ne doit jamais échouer.
        migrator_verify_fixed_totals('inconnue-2099', ['l1_wins' => 0, 'l1_draws' => 0, 'l1_losses' => 0, 'l1_goals_for' => 0, 'l1_goals_against' => 0, 'l1_individual_goals' => 0]);
        $this->assertTrue(true, 'aucune exception levée en l\'absence de season_totals.php');
    }

    public function testTotauxFigesEchouentSiIncoherentsAvecLeFichier(): void
    {
        // 2025-26/season_totals.php existe déjà (Step 3) : un rapport volontairement faux doit échouer.
        $this->assertThrows(
            static fn () => migrator_verify_fixed_totals('2025-26', [
                'l1_wins' => 0, 'l1_draws' => 0, 'l1_losses' => 0,
                'l1_goals_for' => 0, 'l1_goals_against' => 0, 'l1_individual_goals' => 0,
            ]),
            RuntimeException::class,
            'totaux volontairement faux face à 2025-26/season_totals.php'
        );
    }
}
