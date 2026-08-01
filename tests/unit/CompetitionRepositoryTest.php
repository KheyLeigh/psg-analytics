<?php
declare(strict_types=1);
// Vérifie le bilan V/N/D et les buts, en particulier la logique CASE côté domicile/extérieur.
final class CompetitionRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE competitions (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE matches (id INTEGER PRIMARY KEY, competition_id INT, home_team_id INT, away_team_id INT, home_goals INT, away_goals INT)');
        $pdo->exec("INSERT INTO competitions VALUES (1,'Ligue 1')");
        // PSG (id 1) alterne domicile/extérieur sur les trois issues possibles.
        $pdo->exec(
            'INSERT INTO matches (id,competition_id,home_team_id,away_team_id,home_goals,away_goals) VALUES
            (1,1,1,2,3,1),  -- victoire PSG à domicile
            (2,1,3,1,0,2),  -- victoire PSG à l\'extérieur
            (3,1,1,4,0,2),  -- défaite PSG à domicile
            (4,1,2,1,3,0),  -- défaite PSG à l\'extérieur
            (5,1,1,3,1,1),  -- nul PSG à domicile
            (6,1,4,1,2,2)   -- nul PSG à l\'extérieur'
        );
        return $pdo;
    }

    public function testStandingsCalculeVNDEtButsSelonLeCote(): void
    {
        $repo = new CompetitionRepository($this->pdo());
        $rows = $repo->standings(1);

        $this->assertCount(1, $rows, 'une seule compétition');
        $row = $rows[0];

        $this->assertSame(1, $row['competitionId']);
        $this->assertSame('Ligue 1', $row['competitionName']);
        $this->assertSame(2, $row['wins'], 'victoires domicile + extérieur');
        $this->assertSame(2, $row['draws'], 'nuls domicile + extérieur');
        $this->assertSame(2, $row['losses'], 'défaites domicile + extérieur');
        $this->assertSame(8, $row['goalsFor'], 'buts marqués selon le côté de PSG');
        $this->assertSame(9, $row['goalsAgainst'], 'buts encaissés selon le côté de PSG');
    }
}
