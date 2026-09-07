<?php
declare(strict_types=1);
// Vérifie coverageByTable() : taux de vérification filtré par saison (sources()
// reste un catalogue global, déjà couvert par MethodologyControllerTest via une
// doublure).
final class SourceRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE data_sources (id INTEGER PRIMARY KEY, confidence TEXT)");
        $pdo->exec("CREATE TABLE matches (id INTEGER PRIMARY KEY, season_id INT, source_id INT)");
        $pdo->exec("CREATE TABLE player_season_stats (id INTEGER PRIMARY KEY, season_id INT, source_id INT)");
        $pdo->exec("CREATE TABLE player_match_stats (id INTEGER PRIMARY KEY, match_id INT, source_id INT)");
        $pdo->exec("INSERT INTO data_sources VALUES (1,'verified'),(2,'estimated')");
        // Saison 10 : 2 matchs vérifiés. Saison 20 : 1 match, non vérifié (ne doit pas compter dans la saison 10).
        $pdo->exec("INSERT INTO matches VALUES (1,10,1),(2,10,1),(3,20,2)");
        $pdo->exec("INSERT INTO player_season_stats VALUES (1,10,1),(2,20,2)");
        $pdo->exec("INSERT INTO player_match_stats VALUES (1,1,2),(2,2,2)");
        return $pdo;
    }

    public function testCoverageByTableFiltreParSaison(): void
    {
        $repo = new SourceRepository($this->pdo());
        $rows = $repo->coverageByTable(10);
        $byLabel = [];
        foreach ($rows as $r) {
            $byLabel[$r['label']] = $r;
        }
        $this->assertSame(2, $byLabel['Matchs (score, possession, affluence)']['total'], 'seule la saison 10 est comptée');
        $this->assertSame(100, $byLabel['Matchs (score, possession, affluence)']['pct']);
        $this->assertSame(1, $byLabel['Bilans joueurs (toutes compétitions)']['total']);
        $this->assertSame(2, $byLabel['Statistiques par match (attribution)']['total'], 'via jointure matches, filtré saison 10');
        $this->assertSame(0, $byLabel['Statistiques par match (attribution)']['pct'], 'source estimée');
    }
}
