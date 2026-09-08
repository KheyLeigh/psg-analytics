<?php
declare(strict_types=1);

// Vérifie l'assemblage de la page Joueurs (buildViewData), isolé du rendu : liste
// blanche de tri/ordre/poste respectée côté serveur, pagination bornée, méta calculée,
// items réduits aux champs d'identité, et navigation de saison exposée. Vérifie aussi
// la fiche joueur (buildDetail) : profil normalisé et liste des saisons jouées.
final class PlayerControllerTest extends TestCase
{
    private function repo(): PlayerRepository
    {
        return new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public array $seen = [];
            public function paginate(int $seasonId, int $page, int $perPage, string $sort, string $order, ?string $position): array
            {
                $this->seen = compact('seasonId', 'page', 'perPage', 'sort', 'order', 'position');
                $player = Player::fromRow([
                    'id' => 22, 'season_id' => 1, 'person_id' => 22, 'shirt_number' => 29, 'first_name' => 'Bradley',
                    'last_name' => 'Barcola', 'position' => 'FW', 'detailed_position' => 'LW',
                    'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
                    'height_cm' => 182, 'is_captain' => 0,
                ]);
                return ['items' => [$player], 'total' => 24];
            }
        };
    }

    private function saison(): Season
    {
        return new Season(1, '2025-26', '2025-07-01', '2026-06-30', true);
    }

    private function seasonsDouble(): SeasonRepository
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $pdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',1)");
        return new SeasonRepository($pdo);
    }

    public function testValeursParDefaut(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData([], $this->saison());
        $this->assertSame('last_name', $data['sort']);
        $this->assertSame('ASC', $data['order']);
        $this->assertSame(null, $data['position']);
        $this->assertSame(1, $data['meta']['page']);
        $this->assertSame(12, $data['meta']['per_page']);
        $this->assertSame(1, $repo->seen['seasonId']);
    }

    public function testTriHorsListeBlancheRejete(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['sort' => 'goals', 'order' => 'sideways'], $this->saison());
        $this->assertSame('last_name', $repo->seen['sort']);
        $this->assertSame('ASC', $repo->seen['order']);
        $this->assertSame('last_name', $data['sort']);
    }

    public function testOrderDescConserve(): void
    {
        $repo = $this->repo();
        (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['sort' => 'shirt_number', 'order' => 'desc'], $this->saison());
        $this->assertSame('shirt_number', $repo->seen['sort']);
        $this->assertSame('DESC', $repo->seen['order']);
    }

    public function testPositionListeBlanche(): void
    {
        $repo = $this->repo();
        (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['position' => 'ZZ'], $this->saison());
        $this->assertSame(null, $repo->seen['position']);

        $repo2 = $this->repo();
        (new PlayerController($repo2, null, $this->seasonsDouble()))->buildViewData(['position' => 'FW'], $this->saison());
        $this->assertSame('FW', $repo2->seen['position']);
    }

    public function testPerPagePlafonneA50(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['per_page' => '9999'], $this->saison());
        $this->assertSame(50, $data['meta']['per_page']);
        $this->assertSame(50, $repo->seen['perPage']);
    }

    public function testTotalPagesCalcule(): void
    {
        $data = (new PlayerController($this->repo(), null, $this->seasonsDouble()))->buildViewData(['per_page' => '12'], $this->saison());
        $this->assertSame(2, $data['meta']['total_pages']);
        $this->assertSame(24, $data['meta']['total']);
    }

    public function testItemsReduitsAIdentite(): void
    {
        $data = (new PlayerController($this->repo(), null, $this->seasonsDouble()))->buildViewData([], $this->saison());
        $item = $data['players'][0];
        $this->assertSame(['id', 'number', 'name', 'position', 'nationality'], array_keys($item));
        $this->assertSame('Bradley Barcola', $item['name']);
    }

    public function testPageAuDelaDuTotalRameneeALaDernierePage(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['page' => '99', 'per_page' => '12'], $this->saison());
        $this->assertSame(2, $data['meta']['total_pages']);
        $this->assertSame(2, $data['meta']['page']);
        $this->assertSame(99, $repo->seen['page']);
    }

    public function testOrderForgeEnTableauNeDeclencheAucunWarning(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo, null, $this->seasonsDouble()))->buildViewData(['order' => ['x']], $this->saison());
        $this->assertSame('ASC', $data['order']);
        $this->assertSame('ASC', $repo->seen['order']);
    }

    public function testExposeLaNavigationDeSaison(): void
    {
        $data = (new PlayerController($this->repo(), null, $this->seasonsDouble()))->buildViewData([], $this->saison());
        $this->assertSame('2025-26', $data['selectedSeason']);
    }

    public function testFicheJoueurIntrouvableRenvoieNull(): void
    {
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player { return null; }
        };
        $ctrl = new PlayerController($players, $this->statsDouble());
        $this->assertSame(null, $ctrl->buildDetail(999));
    }

    public function testFicheJoueurNormaliseLeProfilContreLEffectif(): void
    {
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player
            {
                return Player::fromRow([
                    'id' => 29, 'season_id' => 1, 'person_id' => 42, 'shirt_number' => 29, 'first_name' => 'Bradley',
                    'last_name' => 'Barcola', 'position' => 'FW', 'detailed_position' => 'LW',
                    'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
                    'height_cm' => 182, 'is_captain' => 0,
                ]);
            }
            public function seasonsForPerson(int $personId): array
            {
                return [['label' => '2025-26', 'isCurrent' => false, 'playerId' => 29]];
            }
        };
        $data = (new PlayerController($players, $this->statsDouble()))->buildDetail(29);

        $this->assertSame('Bradley Barcola', $data['player']['name']);
        $this->assertSame(['Buts', 'Passes déc.', 'Minutes', 'Tirs', 'Tacles gagnés', 'Note'], $data['profile']['axes']);
        $this->assertSame([0.5, 0.5, 1.0, 0.5, 0.5, 1.0], $data['profile']['values']);
        $this->assertSame(1, count($data['timeline']));
        $this->assertSame([['label' => '2025-26', 'isCurrent' => false, 'playerId' => 29]], $data['seasons']);
    }

    public function testShotmapPathDependDeLaSaison(): void
    {
        $this->assertSame(
            BASE_PATH . '/database/seeds/verified/2025-26/understat-shots-2025.json',
            PlayerController::shotmapPath('2025-26')
        );
        $this->assertSame(
            BASE_PATH . '/database/seeds/verified/2026-27/understat-shots-2026.json',
            PlayerController::shotmapPath('2026-27')
        );
    }

    public function testShotsByCompetitionPathDependDeLaSaison(): void
    {
        $this->assertSame(
            BASE_PATH . '/database/seeds/verified/2025-26/fbref-shots-by-competition-2025.json',
            PlayerController::shotsByCompetitionPath('2025-26')
        );
    }

    public function testFicheJoueurSansFichierDeTirsPourSaSaisonRenvoieShotmapNull(): void
    {
        // 2026-27 n'a pas encore de fichier de tirs : la fiche doit rester
        // silencieuse (section masquée), jamais planter.
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player
            {
                return Player::fromRow([
                    'id' => 1, 'season_id' => 2, 'person_id' => 1, 'shirt_number' => 9, 'first_name' => 'Joueur',
                    'last_name' => 'DeuxMilleVingtSix', 'position' => 'FW', 'detailed_position' => 'ST',
                    'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
                    'height_cm' => 180, 'is_captain' => 0,
                ]);
            }
            public function seasonsForPerson(int $personId): array { return []; }
        };
        $seasonsPdo = new PDO('sqlite::memory:');
        $seasonsPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $seasonsPdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $seasonsPdo->exec("INSERT INTO seasons VALUES (2,'2026-27','2026-07-01','2027-06-30',1)");
        $seasons = new SeasonRepository($seasonsPdo);

        $ctrl = new PlayerController($players, $this->statsDouble(), $seasons);
        $data = $ctrl->buildDetail(1);

        $this->assertSame(null, $data['shotmap']);
        $this->assertSame(null, $data['shotsByComp']);
    }

    public function testFicheJoueurAvecFichierDeTirsReelPourSaSaisonRenvoieUnShotmap(): void
    {
        // Achraf Hakimi, id réel 4 dans verified/2025-26/understat-shots-2025.json
        // (27 tirs) : preuve que le fichier déplacé au Step 6 est bien retrouvé.
        $players = new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public function find(int $id): ?Player
            {
                return Player::fromRow([
                    'id' => 4, 'season_id' => 1, 'person_id' => 4, 'shirt_number' => 2, 'first_name' => 'Achraf',
                    'last_name' => 'Hakimi', 'position' => 'DF', 'detailed_position' => 'RB',
                    'foot' => 'right', 'nationality' => 'Maroc', 'birth_date' => null,
                    'height_cm' => 181, 'is_captain' => 0,
                ]);
            }
            public function seasonsForPerson(int $personId): array { return []; }
        };
        $seasonsPdo = new PDO('sqlite::memory:');
        $seasonsPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $seasonsPdo->exec('CREATE TABLE seasons (id INTEGER PRIMARY KEY, label TEXT, start_date TEXT, end_date TEXT, is_current INT)');
        $seasonsPdo->exec("INSERT INTO seasons VALUES (1,'2025-26','2025-07-01','2026-06-30',0)");
        $seasons = new SeasonRepository($seasonsPdo);

        $ctrl = new PlayerController($players, $this->statsDouble(), $seasons);
        $data = $ctrl->buildDetail(4);

        $this->assertTrue($data['shotmap'] !== null, 'le fichier déplacé en 2025-26/ est retrouvé');
        $this->assertSame(27, count($data['shotmap']['shots']));
    }

    // Doublure de StatisticRepository : totaux, maxima d'effectif et timeline cannés.
    private function statsDouble(): StatisticRepository
    {
        return new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function seasonTotalsByPlayer(int $playerId): array
            {
                return ['goals' => 11, 'assists' => 5, 'minutes' => 2000, 'shots' => 40, 'duelsWon' => 30, 'rating' => 7.2];
            }
            public function squadAxisMax(int $seasonId): array
            {
                return ['goals' => 22.0, 'assists' => 10.0, 'minutes' => 2000.0, 'shots' => 80.0, 'duelsWon' => 60.0, 'rating' => 7.2];
            }
            public function timeline(int $playerId): array
            {
                return [['matchId' => 1, 'playedAt' => '2025-08-01', 'goals' => 1, 'assists' => 0, 'minutes' => 90, 'rating' => 7.0]];
            }
        };
    }
}
