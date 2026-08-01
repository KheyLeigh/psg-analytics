<?php
declare(strict_types=1);
// Vérifie la lecture et la pagination filtrée du PlayerRepository.
final class PlayerRepositoryTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT)");
        $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, season_id INT, shirt_number INT, first_name TEXT, last_name TEXT, position TEXT, detailed_position TEXT, foot TEXT, nationality TEXT, birth_date TEXT, height_cm INT, is_captain INT)");
        $pdo->exec("INSERT INTO players VALUES (1,1,29,'Bradley','Barcola','FW','LW','right','France','2002-09-02',182,0)");
        $pdo->exec("INSERT INTO players VALUES (2,1,5,'','Marquinhos','DF','CB','right','Brésil','1994-05-14',183,1)");
        return $pdo;
    }

    public function testFindRetourneJoueur(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $p = $repo->find(1);
        $this->assertTrue($p instanceof Player, 'trouvé');
        $this->assertSame('Bradley Barcola', $p->fullName());
    }

    public function testPaginateFiltrePosition(): void
    {
        $repo = new PlayerRepository($this->pdo());
        $res = $repo->paginate(1, 10, 'last_name', 'ASC', 'DF');
        $this->assertSame(1, $res['total']);
        $this->assertSame('Marquinhos', $res['items'][0]->lastName);
    }
}
