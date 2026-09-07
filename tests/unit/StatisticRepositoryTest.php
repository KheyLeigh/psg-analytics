<?php
declare(strict_types=1);
// Vérifie l'agrégat des buteurs (SUM groupé, tri, filtre compétition et saison).
final class StatisticRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT, person_id INT, shirt_number INT, first_name TEXT, last_name TEXT, position TEXT, detailed_position TEXT, foot TEXT, nationality TEXT, birth_date TEXT, height_cm INT, is_captain INT)");
        $pdo->exec("CREATE TABLE matches (id INTEGER PRIMARY KEY, season_id INT, competition_id INT, played_at TEXT)");
        $pdo->exec("CREATE TABLE player_match_stats (id INTEGER PRIMARY KEY, player_id INT, match_id INT, goals INT, assists INT, minutes INT, shots INT, duels_won INT, rating REAL)");
        $pdo->exec("INSERT INTO players VALUES (1,1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0),(2,1,2,10,'Ousmane','Dembélé','FW','CF','both','France','1997-05-15',178,0)");
        $pdo->exec("INSERT INTO matches VALUES (1,1,1,'2025-08-01'),(2,1,1,'2025-08-02'),(3,2,1,'2025-09-01')");
        // Match 3 appartient à la saison 2 : ses stats ne doivent jamais compter dans les agrégats saison 1.
        $pdo->exec("INSERT INTO player_match_stats (player_id,match_id,goals,assists,minutes,shots,duels_won,rating) VALUES (1,1,2,1,90,5,3,7.5),(1,2,1,0,90,3,2,6.8),(2,1,1,2,80,4,4,7.0),(1,3,9,9,90,9,9,9.9)");
        return $pdo;
    }

    public function testTopScorersOrdonneEtFiltreParSaison(): void
    {
        $repo = new StatisticRepository($this->pdo());
        $top = $repo->topScorers(1, 5, null);
        $this->assertSame('Barcola', $top[0]['player']->lastName);
        $this->assertSame(3, $top[0]['goals'], 'saison 1 uniquement (2+1), le but de la saison 2 est exclu');
        $this->assertSame('Dembélé', $top[1]['player']->lastName);
    }

    public function testTopScorersAutreSaisonNeVoitQueSonMatch(): void
    {
        $repo = new StatisticRepository($this->pdo());
        $top = $repo->topScorers(2, 5, null);
        $this->assertCount(1, $top);
        $this->assertSame(9, $top[0]['goals']);
    }

    public function testSquadAxisMaxFiltreParSaison(): void
    {
        $repo = new StatisticRepository($this->pdo());
        $max = $repo->squadAxisMax(1);
        $this->assertSame(3.0, $max['goals'], 'meilleur total de but sur la saison 1 (Barcola : 2+1)');
    }

    private function pdoComplet(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT, person_id INT, shirt_number INT, first_name TEXT, last_name TEXT, position TEXT, detailed_position TEXT, foot TEXT, nationality TEXT, birth_date TEXT, height_cm INT, is_captain INT)");
        $pdo->exec('CREATE TABLE matches (id INTEGER PRIMARY KEY, season_id INT, played_at TEXT)');
        $pdo->exec(
            'CREATE TABLE player_match_stats (
                id INTEGER PRIMARY KEY, player_id INT, match_id INT, is_starter INT, minutes INT,
                goals INT, assists INT, shots INT, shots_on_target INT, passes INT, pass_accuracy REAL,
                duels_won INT, interceptions INT, yellow_cards INT, red_card INT, saves INT, goals_conceded INT,
                rating REAL, xg REAL, xag REAL, source_id INT
            )'
        );
        return $pdo;
    }

    public function testByMatchAssocieIdentiteJoueurSansEcraserIdDuStat(): void
    {
        $pdo = $this->pdoComplet();
        $pdo->exec("INSERT INTO players VALUES (1,1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0)");
        $pdo->exec("INSERT INTO players VALUES (2,1,2,10,'Ousmane','Dembélé','FW','CF','both','France','1997-05-15',178,0)");
        $pdo->exec("INSERT INTO matches VALUES (10,1,'2025-01-01')");
        $pdo->exec(
            'INSERT INTO player_match_stats VALUES
            (100,1,10,1,90,2,1,4,2,38,80.5,5,4,0,0,0,0,7.5,0.6,0.3,1),
            (101,2,10,1,70,0,2,1,0,20,70.0,3,2,1,0,0,0,6.8,0.1,0.4,1)'
        );

        $repo = new StatisticRepository($pdo);
        $rows = $repo->byMatch(10);

        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0]['player']->id, 'identité du joueur');
        $this->assertSame('Barcola', $rows[0]['player']->lastName);
        $this->assertSame(100, $rows[0]['stat']->id, "l'id du stat n'est pas écrasé par l'id du joueur");
        $this->assertSame(1, $rows[0]['stat']->playerId);
        $this->assertSame(2, $rows[0]['stat']->goals);
        $this->assertSame(1, $rows[0]['stat']->assists);

        $this->assertSame(2, $rows[1]['player']->id, 'identité du joueur');
        $this->assertSame('Dembélé', $rows[1]['player']->lastName);
        $this->assertSame(101, $rows[1]['stat']->id, "l'id du stat n'est pas écrasé par l'id du joueur");
        $this->assertSame(2, $rows[1]['stat']->playerId);
        $this->assertSame(0, $rows[1]['stat']->goals);
        $this->assertSame(2, $rows[1]['stat']->assists);
    }

    public function testTimelineExposeLesPassesDecisivesPasLesPassesTotales(): void
    {
        $pdo = $this->pdoComplet();
        $pdo->exec("INSERT INTO players VALUES (1,1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0)");
        $pdo->exec("INSERT INTO matches VALUES (20,1,'2025-02-01')");
        $pdo->exec(
            "INSERT INTO player_match_stats VALUES
            (200,1,20,1,90,1,3,4,2,45,80.0,5,3,0,0,0,0,7.2,0.5,0.4,1)"
        );

        $repo = new StatisticRepository($pdo);
        $rows = $repo->timeline(1);

        $this->assertCount(1, $rows);
        $this->assertSame(3, $rows[0]['assists'], 'expose les passes décisives (assists), pas les passes totales');
        $this->assertTrue(!array_key_exists('passes', $rows[0]), "ne conserve pas une clé 'passes' qui contiendrait en réalité les assists");
    }

    public function testGoalsByPlayerAndMonthFiltreParSaison(): void
    {
        $pdo = $this->pdo();
        $rows = (new StatisticRepository($pdo))->goalsByPlayerAndMonth(1);
        $total = array_sum(array_column($rows, 'goals'));
        $this->assertSame(4, $total, 'saison 1 uniquement : 2+1 (Barcola) + 1 (Dembélé), le 9 de la saison 2 est exclu');
    }
}
