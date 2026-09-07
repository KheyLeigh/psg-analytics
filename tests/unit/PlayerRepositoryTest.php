<?php
declare(strict_types=1);
// Vérifie la lecture, la pagination filtrée par saison, et la résolution des
// saisons jouées par un même joueur (person_id) du PlayerRepository.
final class PlayerRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)");
        $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT, person_id INT, shirt_number INT, first_name TEXT, last_name TEXT, position TEXT, detailed_position TEXT, foot TEXT, nationality TEXT, birth_date TEXT, height_cm INT, is_captain INT)");
        $pdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',0)");
        $pdo->exec("INSERT INTO seasons VALUES (2,'2026-27','2026-07-01','2027-06-30',1)");
        // Barcola (person 1) joue les deux saisons ; Marquinhos (person 2) ne joue que 2025-26.
        $pdo->exec("INSERT INTO players VALUES (1,1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0)");
        $pdo->exec("INSERT INTO players VALUES (2,1,2,5,'','Marquinhos','DF','CB','right','Brésil','1994-05-14',183,1)");
        $pdo->exec("INSERT INTO players VALUES (3,2,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0)");
        return $pdo;
    }

    public function testFindRetourneJoueur(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $p = $repo->find(1);
        $this->assertTrue($p instanceof Player, 'trouvé');
        $this->assertSame('Bradley Barcola', $p->fullName());
    }

    public function testAllFiltreParSaison(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $this->assertCount(2, $repo->all(1), '2025-26 : Barcola + Marquinhos');
        $this->assertCount(1, $repo->all(2), '2026-27 : Barcola seul');
    }

    public function testPaginateFiltrePositionEtSaison(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $res = $repo->paginate(1, 1, 10, 'last_name', 'ASC', 'DF');
        $this->assertSame(1, $res['total']);
        $this->assertSame('Marquinhos', $res['items'][0]->lastName);

        // Même filtre position, mais saison 2026-27 : Marquinhos n'y joue pas.
        $res2026 = $repo->paginate(2, 1, 10, 'last_name', 'ASC', 'DF');
        $this->assertSame(0, $res2026['total'], 'Marquinhos absent de 2026-27');
    }

    public function testSeasonsForPersonRenvoieLesSaisonsJoueesTrieesParDate(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $seasons = $repo->seasonsForPerson(1);

        $this->assertCount(2, $seasons, 'Barcola a joué deux saisons');
        $this->assertSame(['2025-26', '2026-27'], array_column($seasons, 'label'));
        $this->assertSame(1, $seasons[0]['playerId']);
        $this->assertSame(3, $seasons[1]['playerId']);
        $this->assertSame(false, $seasons[0]['isCurrent']);
        $this->assertSame(true, $seasons[1]['isCurrent']);
    }

    public function testSeasonsForPersonRenvoieUneSeuleSaisonSiJoueeUneFois(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $seasons = $repo->seasonsForPerson(2);
        $this->assertCount(1, $seasons);
        $this->assertSame('2025-26', $seasons[0]['label']);
    }
}
