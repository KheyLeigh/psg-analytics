<?php
declare(strict_types=1);

// Vérifie l'assemblage de la page Joueurs (buildViewData), isolé du rendu : liste
// blanche de tri/ordre/poste respectée côté serveur, pagination bornée, méta calculée,
// et items réduits aux champs d'identité (mêmes champs que /api/players).
final class PlayerControllerTest extends TestCase
{
    // Doublure du repository : capture les arguments reçus et renvoie un effectif canned.
    private function repo(): PlayerRepository
    {
        return new class(new PDO('sqlite::memory:')) extends PlayerRepository {
            public array $seen = [];
            public function paginate(int $page, int $perPage, string $sort, string $order, ?string $position): array
            {
                $this->seen = compact('page', 'perPage', 'sort', 'order', 'position');
                $player = Player::fromRow([
                    'id' => 22, 'season_id' => 1, 'shirt_number' => 29, 'first_name' => 'Bradley',
                    'last_name' => 'Barcola', 'position' => 'FW', 'detailed_position' => 'LW',
                    'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
                    'height_cm' => 182, 'is_captain' => 0,
                ]);
                return ['items' => [$player], 'total' => 24];
            }
        };
    }

    public function testValeursParDefaut(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo))->buildViewData([]);
        $this->assertSame('last_name', $data['sort']);
        $this->assertSame('ASC', $data['order']);
        $this->assertSame(null, $data['position']);
        $this->assertSame(1, $data['meta']['page']);
        $this->assertSame(12, $data['meta']['per_page']);
    }

    public function testTriHorsListeBlancheRejete(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo))->buildViewData(['sort' => 'goals', 'order' => 'sideways']);
        // Le contrôleur ne transmet jamais une valeur hors liste blanche au repository.
        $this->assertSame('last_name', $repo->seen['sort']);
        $this->assertSame('ASC', $repo->seen['order']);
        $this->assertSame('last_name', $data['sort']);
    }

    public function testOrderDescConserve(): void
    {
        $repo = $this->repo();
        (new PlayerController($repo))->buildViewData(['sort' => 'shirt_number', 'order' => 'desc']);
        $this->assertSame('shirt_number', $repo->seen['sort']);
        $this->assertSame('DESC', $repo->seen['order']);
    }

    public function testPositionListeBlanche(): void
    {
        $repo = $this->repo();
        (new PlayerController($repo))->buildViewData(['position' => 'ZZ']);
        $this->assertSame(null, $repo->seen['position']);

        $repo2 = $this->repo();
        (new PlayerController($repo2))->buildViewData(['position' => 'FW']);
        $this->assertSame('FW', $repo2->seen['position']);
    }

    public function testPerPagePlafonneA50(): void
    {
        $repo = $this->repo();
        $data = (new PlayerController($repo))->buildViewData(['per_page' => '9999']);
        $this->assertSame(50, $data['meta']['per_page']);
        $this->assertSame(50, $repo->seen['perPage']);
    }

    public function testTotalPagesCalcule(): void
    {
        $data = (new PlayerController($this->repo()))->buildViewData(['per_page' => '12']);
        // 24 joueurs sur 12 par page : deux pages exactement.
        $this->assertSame(2, $data['meta']['total_pages']);
        $this->assertSame(24, $data['meta']['total']);
    }

    public function testItemsReduitsAIdentite(): void
    {
        $data = (new PlayerController($this->repo()))->buildViewData([]);
        $item = $data['players'][0];
        $this->assertSame(['id', 'number', 'name', 'position', 'nationality'], array_keys($item));
        $this->assertSame('Bradley Barcola', $item['name']);
    }

    public function testPageAuDelaDuTotalRameneeALaDernierePage(): void
    {
        $repo = $this->repo();
        // 24 joueurs (doublure fixe) / 12 par page = 2 pages : une page 99 demandée doit
        // être ramenée à la dernière page valide, pour des contrôles de pagination cohérents.
        $data = (new PlayerController($repo))->buildViewData(['page' => '99', 'per_page' => '12']);
        $this->assertSame(2, $data['meta']['total_pages']);
        $this->assertSame(2, $data['meta']['page']);
        // Le repository reçoit toujours la page brute demandée : le clamp n'affecte que
        // les méta-données renvoyées à la vue, pas la requête envoyée en amont.
        $this->assertSame(99, $repo->seen['page']);
    }

    public function testOrderForgeEnTableauNeDeclencheAucunWarning(): void
    {
        $repo = $this->repo();
        // ?order[]=x : un paramètre tableau ne doit jamais atteindre strtoupper() (sinon
        // warning "Array to string conversion") et doit retomber sur la valeur par défaut.
        $data = (new PlayerController($repo))->buildViewData(['order' => ['x']]);
        $this->assertSame('ASC', $data['order']);
        $this->assertSame('ASC', $repo->seen['order']);
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
                    'id' => 29, 'season_id' => 1, 'shirt_number' => 29, 'first_name' => 'Bradley',
                    'last_name' => 'Barcola', 'position' => 'FW', 'detailed_position' => 'LW',
                    'foot' => 'right', 'nationality' => 'France', 'birth_date' => null,
                    'height_cm' => 182, 'is_captain' => 0,
                ]);
            }
        };
        $data = (new PlayerController($players, $this->statsDouble()))->buildDetail(29);

        $this->assertSame('Bradley Barcola', $data['player']['name']);
        $this->assertSame(['Buts', 'Passes déc.', 'Minutes', 'Tirs', 'Duels gagnés', 'Note'], $data['profile']['axes']);
        // Valeur du joueur / meilleur de l'effectif par axe : 11/22, 5/10, 2000/2000,
        // 40/80, 30/60, 7.2/7.2 -> profil normalisé de 0 à 1.
        $this->assertSame([0.5, 0.5, 1.0, 0.5, 0.5, 1.0], $data['profile']['values']);
        $this->assertSame(1, count($data['timeline']));
    }

    // Doublure de StatisticRepository : totaux, maxima d'effectif et timeline cannés.
    private function statsDouble(): StatisticRepository
    {
        return new class(new PDO('sqlite::memory:')) extends StatisticRepository {
            public function seasonTotalsByPlayer(int $playerId): array
            {
                return ['goals' => 11, 'assists' => 5, 'minutes' => 2000, 'shots' => 40, 'duelsWon' => 30, 'rating' => 7.2];
            }
            public function squadAxisMax(): array
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
