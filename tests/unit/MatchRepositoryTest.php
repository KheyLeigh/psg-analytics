<?php
declare(strict_types=1);
// Vérifie le bilan Ligue 1 calculé en une requête (V/N/D, buts, clean sheets, possession).
final class MatchRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE competitions (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE matches (id INTEGER PRIMARY KEY, competition_id INT, home_team_id INT, away_team_id INT, home_goals INT, away_goals INT, psg_possession REAL)');
        $pdo->exec("INSERT INTO competitions VALUES (1,'Ligue 1'),(2,'Coupe de France')");
        // PSG (id 1) : mêmes six issues que CompetitionRepositoryTest, un seul clean sheet
        // (match 2, où PSG encaisse 0), possession partiellement renseignée (NULL sur 3 matchs).
        $pdo->exec(
            'INSERT INTO matches (id,competition_id,home_team_id,away_team_id,home_goals,away_goals,psg_possession) VALUES
            (1,1,1,2,3,1,60),   -- victoire PSG à domicile, encaisse 1
            (2,1,3,1,0,2,NULL), -- victoire PSG à l\'extérieur, encaisse 0 (clean sheet)
            (3,1,1,4,0,2,55),   -- défaite PSG à domicile, encaisse 2
            (4,1,2,1,3,0,NULL), -- défaite PSG à l\'extérieur, encaisse 3
            (5,1,1,3,1,1,50),   -- nul PSG à domicile, encaisse 1
            (6,1,4,1,2,2,NULL), -- nul PSG à l\'extérieur, encaisse 2
            (7,2,1,5,4,0,99)'
            // Le match 7 (Coupe de France) doit être exclu du bilan Ligue 1.
        );
        return $pdo;
    }

    public function testSeasonRecordCalculeLeBilanLigue1(): void
    {
        $repo = new MatchRepository($this->pdo());
        $record = $repo->seasonRecord(1, 1);

        $this->assertSame(6, $record['played'], 'seule Ligue 1 est comptée');
        $this->assertSame(2, $record['wins']);
        $this->assertSame(2, $record['draws']);
        $this->assertSame(2, $record['losses']);
        $this->assertSame(8, $record['goals_for']);
        $this->assertSame(9, $record['goals_against']);
        $this->assertSame(1, $record['clean_sheets'], 'un seul match où PSG encaisse 0');
        $this->assertSame(55.0, $record['avg_possession'], 'moyenne sur les seules valeurs renseignées (60+55+50)/3');
    }

    public function testSeasonRecordGereUnePossessionEntierementNulle(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('UPDATE matches SET psg_possession = NULL');
        $repo = new MatchRepository($pdo);
        $record = $repo->seasonRecord(1, 1);

        $this->assertSame(0.0, $record['avg_possession'], 'COALESCE ramène à 0 quand tout est NULL');
    }

    // Cumul pur : deux victoires, un nul, une défaite depuis le point de vue PSG (id 1).
    // 3, 6, 7, 7 points ; le résultat suit le score et les journées sont numérotées.
    public function testAccumulatePointsCumuleLesPointsParJournee(): void
    {
        $rows = [
            ['round_label' => 'J1', 'home_team_id' => 1, 'away_team_id' => 2, 'home_goals' => 2, 'away_goals' => 0],
            ['round_label' => 'J2', 'home_team_id' => 3, 'away_team_id' => 1, 'home_goals' => 0, 'away_goals' => 1],
            ['round_label' => 'J3', 'home_team_id' => 1, 'away_team_id' => 4, 'home_goals' => 1, 'away_goals' => 1],
            ['round_label' => 'J4', 'home_team_id' => 5, 'away_team_id' => 1, 'home_goals' => 3, 'away_goals' => 0],
        ];
        $series = MatchRepository::accumulatePoints($rows, 1);

        $this->assertCount(4, $series);
        $this->assertSame([1, 3, 'W', 'J1'], [$series[0]['x'], $series[0]['y'], $series[0]['result'], $series[0]['label']]);
        $this->assertSame(6, $series[1]['y'], 'victoire à l\'extérieur : +3');
        $this->assertSame(7, $series[2]['y'], 'match nul : +1');
        $this->assertSame('L', $series[3]['result'], 'défaite : résultat L');
        $this->assertSame(7, $series[3]['y'], 'la défaite n\'ajoute aucun point');
    }
}
