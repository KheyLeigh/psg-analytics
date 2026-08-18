<?php
declare(strict_types=1);

// tests/unit/PlayerControllerTest.php
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
}
