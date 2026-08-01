<?php
declare(strict_types=1);
// Vérifie l'agrégat des buteurs (SUM groupé, tri, filtre compétition).
final class StatisticRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT, shirt_number INT, first_name TEXT, last_name TEXT, position TEXT, detailed_position TEXT, foot TEXT, nationality TEXT, birth_date TEXT, height_cm INT, is_captain INT)");
        $pdo->exec("CREATE TABLE matches (id INTEGER PRIMARY KEY, competition_id INT)");
        $pdo->exec("CREATE TABLE player_match_stats (id INTEGER PRIMARY KEY, player_id INT, match_id INT, goals INT, assists INT, minutes INT)");
        $pdo->exec("INSERT INTO players VALUES (1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0),(2,1,10,'Ousmane','Dembélé','FW','CF','both','France','1997-05-15',178,0)");
        $pdo->exec("INSERT INTO matches VALUES (1,1),(2,1)");
        $pdo->exec("INSERT INTO player_match_stats (player_id,match_id,goals,assists,minutes) VALUES (1,1,2,1,90),(1,2,1,0,90),(2,1,1,2,80)");
        return $pdo;
    }

    public function testTopScorersOrdonne(): void
    {
        $repo = new StatisticRepository($this->pdo());
        $top = $repo->topScorers(5, null);
        $this->assertSame('Barcola', $top[0]['player']->lastName);
        $this->assertSame(3, $top[0]['goals']);
        $this->assertSame('Dembélé', $top[1]['player']->lastName);
    }
}
