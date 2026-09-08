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

    // I1 : la contrainte "une seule saison courante" doit être vérifiée, pas seulement
    // documentée en commentaire. migrator_assert_single_current() prend directement un
    // tableau de saisons déjà chargé, indépendamment de la lecture de verified/seasons.php.
    public function testAssertSingleCurrentEchoueSiAucuneSaisonCourante(): void
    {
        $this->assertThrows(
            static fn () => migrator_assert_single_current([
                ['key' => 'a', 'is_current' => false],
                ['key' => 'b', 'is_current' => false],
            ]),
            RuntimeException::class,
            'zéro saison is_current => true doit échouer'
        );
    }

    public function testAssertSingleCurrentEchoueSiDeuxSaisonsCourantes(): void
    {
        $this->assertThrows(
            static fn () => migrator_assert_single_current([
                ['key' => 'a', 'is_current' => true],
                ['key' => 'b', 'is_current' => true],
            ]),
            RuntimeException::class,
            'deux saisons is_current => true doit échouer'
        );
    }

    public function testAssertSingleCurrentLaisseFairePasserUneSeuleSaisonCourante(): void
    {
        migrator_assert_single_current([
            ['key' => 'a', 'is_current' => false],
            ['key' => 'b', 'is_current' => true],
        ]);
        $this->assertTrue(true, 'aucune exception levée avec exactement une saison courante');
    }

    public function testMigratorSeedSeasonsSurLesDonneesReellesNeLevePasException(): void
    {
        // La migration réelle (2025-26 is_current=false, 2026-27 is_current=true) doit
        // continuer de passer : régression contre le garde-fou nouvellement ajouté.
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $seasons = migrator_seed_seasons($pdo);
        $this->assertSame(2, count($seasons), 'deux saisons déclarées dans verified/seasons.php');
    }

    // I2 : migrator_resolve_person() n'était testée nulle part. Correspondance exacte
    // (pas de normalisation), documentée en commentaire au-dessus de la fonction.
    private function pdoAvecTablePeople(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE people (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');
        return $pdo;
    }

    public function testResolvePersonCreeUneLignePourUnNouveauJoueur(): void
    {
        $pdo = $this->pdoAvecTablePeople();
        $id = migrator_resolve_person($pdo, 'Gianluigi', 'Donnarumma');
        $this->assertSame(1, $id, 'premier insert, id auto-incrémenté à 1');
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM people')->fetchColumn());
    }

    public function testResolvePersonRenvoieLeMemeIdPourLeMemeNomExact(): void
    {
        $pdo = $this->pdoAvecTablePeople();
        $id1 = migrator_resolve_person($pdo, 'Gianluigi', 'Donnarumma');
        $id2 = migrator_resolve_person($pdo, 'Gianluigi', 'Donnarumma');
        $this->assertSame($id1, $id2, 'même prénom/nom exact : pas de doublon');
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM people')->fetchColumn(), 'une seule ligne people');
    }

    public function testResolvePersonCreeUneNouvelleLignePourUnNomDifferent(): void
    {
        $pdo = $this->pdoAvecTablePeople();
        $id1 = migrator_resolve_person($pdo, 'Gianluigi', 'Donnarumma');
        $id2 = migrator_resolve_person($pdo, 'Ousmane', 'Dembélé');
        $this->assertTrue($id1 !== $id2, 'noms différents : identifiants différents');
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM people')->fetchColumn());
    }

    // I3 : la clé de source dédiée à une saison (ex. "fbref_2026-27") doit primer sur
    // la clé partagée (ex. "fbref") quand elle existe, pour que la tâche planifiée de
    // collecte hebdomadaire ne date jamais faussement les entrées de saisons figées.
    public function testResolveSourceIdUtiliseLaCleDedieeSiPresente(): void
    {
        $id = migrator_resolve_source_id(['fbref' => 1, 'fbref_2026-27' => 2], 'fbref', '2026-27');
        $this->assertSame(2, $id, 'la clé dédiée à la saison prime sur la clé partagée');
    }

    public function testResolveSourceIdRetombeSurLaClePartageeSiAbsente(): void
    {
        $id = migrator_resolve_source_id(['fbref' => 1], 'fbref', '2025-26');
        $this->assertSame(1, $id, 'pas de clé fbref_2025-26 : on retombe sur la clé partagée fbref');
    }
}
