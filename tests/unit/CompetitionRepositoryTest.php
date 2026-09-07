<?php
declare(strict_types=1);
// Vérifie le bilan V/N/D et les buts, en particulier la logique CASE côté
// domicile/extérieur, filtré par saison.
final class CompetitionRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE competitions (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE matches (id INTEGER PRIMARY KEY, season_id INT, competition_id INT, home_team_id INT, away_team_id INT, home_goals INT, away_goals INT)');
        $pdo->exec("INSERT INTO competitions VALUES (1,'Ligue 1')");
        // PSG (id 1) alterne domicile/extérieur sur les trois issues possibles, saison 10.
        // Le match 7 (saison 20) doit être exclu du bilan de la saison 10.
        $pdo->exec(
            'INSERT INTO matches (id,season_id,competition_id,home_team_id,away_team_id,home_goals,away_goals) VALUES
            (1,10,1,1,2,3,1),  -- victoire PSG à domicile
            (2,10,1,3,1,0,2),  -- victoire PSG à l\'extérieur
            (3,10,1,1,4,0,2),  -- défaite PSG à domicile
            (4,10,1,2,1,3,0),  -- défaite PSG à l\'extérieur
            (5,10,1,1,3,1,1),  -- nul PSG à domicile
            (6,10,1,4,1,2,2),  -- nul PSG à l\'extérieur
            (7,20,1,1,2,5,0)   -- autre saison : exclu'
        );
        return $pdo;
    }

    public function testStandingsCalculeVNDEtButsSelonLeCoteEtLaSaison(): void
    {
        $repo = new CompetitionRepository($this->pdo());
        $rows = $repo->standings(10, 1);

        $this->assertCount(1, $rows, 'une seule compétition');
        $row = $rows[0];

        $this->assertSame(1, $row['competitionId']);
        $this->assertSame('Ligue 1', $row['competitionName']);
        $this->assertSame(2, $row['wins'], 'victoires domicile + extérieur, saison 10 uniquement');
        $this->assertSame(2, $row['draws'], 'nuls domicile + extérieur');
        $this->assertSame(2, $row['losses'], 'défaites domicile + extérieur');
        $this->assertSame(8, $row['goalsFor'], 'buts marqués selon le côté de PSG, sans le match de la saison 20');
        $this->assertSame(9, $row['goalsAgainst'], 'buts encaissés selon le côté de PSG');
    }
}
