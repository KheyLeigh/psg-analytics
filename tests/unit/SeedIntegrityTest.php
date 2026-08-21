<?php
declare(strict_types=1);
// Contrôle d'intégrité des seeds : identités vérifiées après migration réelle.
require_once dirname(__DIR__, 1) . '/../database/migrate.php'; // expose run_migration()

final class SeedIntegrityTest extends TestCase
{
    private function migratedPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        run_migration($pdo);
        return $pdo;
    }

    public function testBilanLigue1(): void
    {
        $pdo = $this->migratedPdo();
        $psg = (int) $pdo->query("SELECT id FROM teams WHERE is_psg = 1")->fetchColumn();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type = 'league'")->fetchColumn();
        $row = $pdo->query("SELECT
            SUM(CASE WHEN (home_team_id={$psg} AND home_goals>away_goals) OR (away_team_id={$psg} AND away_goals>home_goals) THEN 1 ELSE 0 END) w,
            SUM(CASE WHEN home_goals=away_goals THEN 1 ELSE 0 END) d,
            SUM(CASE WHEN (home_team_id={$psg} AND home_goals<away_goals) OR (away_team_id={$psg} AND away_goals<home_goals) THEN 1 ELSE 0 END) l
            FROM matches WHERE competition_id = {$comp}")->fetch();
        $this->assertSame(24, (int) $row['w'], '24 victoires L1');
        $this->assertSame(4, (int) $row['d'], '4 nuls L1');
        $this->assertSame(6, (int) $row['l'], '6 défaites L1');
    }

    public function testEffectifComplet(): void
    {
        $pdo = $this->migratedPdo();
        $n = (int) $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
        $this->assertSame(24, $n, '24 joueurs');
    }

    public function testButsIndividuelsL1Egalent73AvecUnCscAdverse(): void
    {
        $pdo = $this->migratedPdo();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type='league'")->fetchColumn();
        $indiv = (int) $pdo->query("SELECT SUM(goals) FROM player_match_stats s JOIN matches m ON m.id=s.match_id WHERE m.competition_id={$comp}")->fetchColumn();
        // 74 buts d'équipe - 73 buts individuels = 1 but contre son camp
        // adverse, crédité à PSG mais jamais attribué à un joueur (FBref).
        $this->assertSame(73, $indiv, 'somme buts individuels L1 = 73 (1 csc adverse non attribué)');
    }

    public function testAssistsIndividuellesL1Egalent55(): void
    {
        $pdo = $this->migratedPdo();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type='league'")->fetchColumn();
        $indiv = (int) $pdo->query("SELECT SUM(assists) FROM player_match_stats s JOIN matches m ON m.id=s.match_id WHERE m.competition_id={$comp}")->fetchColumn();
        $this->assertSame(55, $indiv, 'somme passes décisives L1 = 55');
    }

    public function testTirsEtTaclesL1TotauxExacts(): void
    {
        $pdo = $this->migratedPdo();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type='league'")->fetchColumn();
        $row = $pdo->query("SELECT SUM(shots) sh, SUM(shots_on_target) sot, SUM(duels_won) dw
            FROM player_match_stats s JOIN matches m ON m.id=s.match_id WHERE m.competition_id={$comp}")->fetch(PDO::FETCH_ASSOC);
        // Totaux vérifiés FBref (tables Shooting et Miscellaneous) : la répartition
        // par match est estimée mais la somme reste exacte.
        $this->assertSame(599, (int) $row['sh'], 'somme tirs L1 = 599 (FBref Shooting)');
        $this->assertSame(225, (int) $row['sot'], 'somme tirs cadrés L1 = 225 (FBref Shooting)');
        $this->assertSame(316, (int) $row['dw'], 'somme tacles gagnés L1 = 316 (FBref Miscellaneous)');

        // Spot-check par joueur : les totaux saison collent exactement.
        $shots = [];
        foreach ($pdo->query("SELECT p.last_name ln, p.first_name fn, SUM(s.shots) sh
            FROM player_match_stats s JOIN players p ON p.id=s.player_id JOIN matches m ON m.id=s.match_id
            WHERE m.competition_id={$comp} GROUP BY p.id") as $r) {
            $shots[$r['ln'] !== '' ? $r['ln'] : $r['fn']] = (int) $r['sh'];
        }
        $this->assertSame(66, $shots['Barcola'] ?? 0, 'Barcola exactement 66 tirs L1');
        $this->assertSame(45, $shots['Dembélé'] ?? 0, 'Dembélé exactement 45 tirs L1');
    }

    public function testAssistsVitinhaEtDembeleExacts(): void
    {
        $pdo = $this->migratedPdo();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type='league'")->fetchColumn();
        $rows = $pdo->query("SELECT p.last_name ln, p.first_name fn, SUM(s.assists) a
            FROM player_match_stats s
            JOIN players p ON p.id = s.player_id
            JOIN matches m ON m.id = s.match_id
            WHERE m.competition_id = {$comp}
            GROUP BY p.id")->fetchAll();
        $assists = [];
        foreach ($rows as $r) {
            $key = $r['ln'] !== '' ? $r['ln'] : $r['fn'];
            $assists[$key] = (int) $r['a'];
        }
        $this->assertSame(7, $assists['Vitinha'] ?? 0, 'Vitinha exactement 7 passes L1');
        $this->assertSame(7, $assists['Dembélé'] ?? 0, 'Dembélé exactement 7 passes L1');
    }

    public function testCartonsRougesEtJaunesL1Exacts(): void
    {
        $pdo = $this->migratedPdo();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type='league'")->fetchColumn();
        $totalReds = (int) $pdo->query("SELECT SUM(red_card) FROM player_match_stats s JOIN matches m ON m.id=s.match_id WHERE m.competition_id={$comp}")->fetchColumn();
        $this->assertSame(2, $totalReds, 'total cartons rouges L1 = 2 (Hakimi, Ramos)');

        $rows = $pdo->query("SELECT p.last_name ln, p.first_name fn, SUM(s.yellow_cards) y
            FROM player_match_stats s
            JOIN players p ON p.id = s.player_id
            JOIN matches m ON m.id = s.match_id
            WHERE m.competition_id = {$comp}
            GROUP BY p.id")->fetchAll();
        $yellows = [];
        foreach ($rows as $r) {
            $key = $r['ln'] !== '' ? $r['ln'] : $r['fn'];
            $yellows[$key] = (int) $r['y'];
        }
        $this->assertSame(6, $yellows['Zabarnyi'] ?? 0, 'Zabarnyi exactement 6 jaunes L1');
    }

    public function testGenerationPlayerMatchStatsDeterministe(): void
    {
        $dump = static function (): array {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            run_migration($pdo);
            return $pdo->query(
                'SELECT player_id, match_id, is_starter, minutes, goals, assists, yellow_cards, red_card, rating
                 FROM player_match_stats ORDER BY player_id, match_id'
            )->fetchAll();
        };
        $this->assertSame($dump(), $dump(), 'deux migrations successives produisent des player_match_stats identiques');
    }

    /** Buts L1 par joueur, indexés par nom de famille (ou prénom si mononyme). */
    private function goalsByPlayer(PDO $pdo): array
    {
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type='league'")->fetchColumn();
        $rows = $pdo->query("SELECT p.last_name ln, p.first_name fn, SUM(s.goals) g
            FROM player_match_stats s
            JOIN players p ON p.id = s.player_id
            JOIN matches m ON m.id = s.match_id
            WHERE m.competition_id = {$comp}
            GROUP BY p.id")->fetchAll();
        $goals = [];
        foreach ($rows as $r) {
            $key = $r['ln'] !== '' ? $r['ln'] : $r['fn'];
            $goals[$key] = (int) $r['g'];
        }
        return $goals;
    }

    public function testTotauxButeursVerifiesExacts(): void
    {
        $goals = $this->goalsByPlayer($this->migratedPdo());
        $this->assertSame(11, $goals['Barcola'] ?? 0, 'Barcola exactement 11 buts L1');
        $this->assertSame(10, $goals['Dembélé'] ?? 0, 'Dembélé exactement 10');
        $this->assertSame(8, $goals['Kvaratskhelia'] ?? 0, 'Kvaratskhelia exactement 8');
        $this->assertSame(7, $goals['Doué'] ?? 0, 'Doué exactement 7');
        $this->assertSame(6, $goals['Ramos'] ?? 0, 'Ramos exactement 6');
    }

    public function testAucunButeurNeDepasseBarcola(): void
    {
        $goals = $this->goalsByPlayer($this->migratedPdo());
        $this->assertTrue(max($goals) <= 11, 'aucun buteur au-dessus de 11 (Barcola en tête)');
    }

    public function testPossessionMoyenneL1Realiste(): void
    {
        $pdo = $this->migratedPdo();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type = 'league'")->fetchColumn();
        $avg = (float) $pdo->query("SELECT AVG(psg_possession) FROM matches WHERE competition_id = {$comp}")->fetchColumn();
        $this->assertTrue($avg >= 66 && $avg <= 72, "possession moyenne L1 réaliste (obtenu {$avg})");
    }

    public function testAffluenceEtPossessionRenseigneesSurTousLesMatchsL1(): void
    {
        $pdo = $this->migratedPdo();
        $comp = (int) $pdo->query("SELECT id FROM competitions WHERE type = 'league'")->fetchColumn();
        $rows = $pdo->query("SELECT attendance, psg_possession FROM matches WHERE competition_id = {$comp}")->fetchAll();
        $this->assertSame(34, count($rows), '34 matchs de Ligue 1');
        foreach ($rows as $row) {
            $this->assertTrue((int) $row['attendance'] > 0, 'affluence non nulle et positive');
            $poss = (float) $row['psg_possession'];
            $this->assertTrue($poss >= 30 && $poss <= 95, "possession plausible (obtenu {$poss})");
        }
    }

    public function testMatchweek21ContreMarseille(): void
    {
        $pdo = $this->migratedPdo();
        $psg = (int) $pdo->query("SELECT id FROM teams WHERE is_psg = 1")->fetchColumn();
        $row = $pdo->query("SELECT home_goals, away_goals, attendance, psg_possession
            FROM matches WHERE round_label = 'J21' AND home_team_id = {$psg}")->fetch();
        $this->assertSame(5, (int) $row['home_goals'], 'PSG marque 5 buts face à Marseille (J21)');
        $this->assertSame(0, (int) $row['away_goals'], 'Marseille encaisse 5-0 (J21)');
        $this->assertSame(47926, (int) $row['attendance'], 'affluence J21 vérifiée FBref');
        $this->assertSame(58.0, (float) $row['psg_possession'], 'possession J21 vérifiée FBref');
    }

    public function testTotalMatchsToutesCompetitions(): void
    {
        $pdo = $this->migratedPdo();
        $n = (int) $pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn();
        $this->assertSame(55, $n, '34 matchs L1 + 21 matchs hors L1 (Supercoupe, C1, Trophée, Coupe de France)');
    }

    public function testMatchsHorsL1OntUneCompetitionEtUnVenueValides(): void
    {
        $pdo = $this->migratedPdo();
        $leagueComp = (int) $pdo->query("SELECT id FROM competitions WHERE type = 'league'")->fetchColumn();
        $rows = $pdo->query("SELECT m.competition_id, m.venue FROM matches m WHERE m.competition_id != {$leagueComp}")->fetchAll();
        $this->assertSame(21, count($rows), '21 matchs hors Ligue 1');
        foreach ($rows as $row) {
            $this->assertTrue((int) $row['competition_id'] > 0, 'competition_id valide');
            $this->assertTrue(in_array($row['venue'], ['home', 'away', 'neutral'], true), 'venue valide');
        }
    }
}
