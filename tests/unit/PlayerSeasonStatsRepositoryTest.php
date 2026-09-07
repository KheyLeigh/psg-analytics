<?php
declare(strict_types=1);
// Vérifie que all() filtre par saison (forPlayer() est déjà couvert par
// PlayerSeasonStatsTest via une migration réelle).
final class PlayerSeasonStatsRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE player_season_stats (id INTEGER PRIMARY KEY, player_id INT, season_id INT, appearances INT, starts INT, goals INT, assists INT, yellow_cards INT, red_cards INT, source_id INT)');
        $pdo->exec('INSERT INTO player_season_stats (player_id,season_id,appearances,starts,goals,assists,yellow_cards,red_cards,source_id) VALUES
            (1,1,49,35,13,6,3,0,1),
            (2,1,30,20,2,1,1,0,1),
            (3,2,10,10,4,0,0,0,1)');
        return $pdo;
    }

    public function testAllFiltreParSaisonEtTrieParButs(): void
    {
        $repo = new PlayerSeasonStatsRepository($this->pdo());
        $bilans = $repo->all(1);
        $this->assertCount(2, $bilans, 'saison 1 uniquement');
        $this->assertSame(13, $bilans[1]->goals, 'indexé par player_id, Barcola (id 1) en tête');
    }

    public function testAllAutreSaisonNeVoitQueSonJoueur(): void
    {
        $repo = new PlayerSeasonStatsRepository($this->pdo());
        $bilans = $repo->all(2);
        $this->assertCount(1, $bilans);
        $this->assertSame(4, $bilans[3]->goals);
    }
}
