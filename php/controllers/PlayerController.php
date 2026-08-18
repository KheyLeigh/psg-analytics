<?php

declare(strict_types=1);

// Page Joueurs : le navigateur d'effectif. La coquille et la PREMIERE PAGE sont
// rendues côté serveur (SSR) pour rester navigables sans JavaScript : le tri, le
// filtre par poste et la pagination passent par des liens ?sort/order/position/page
// que le contrôleur relit et valide par liste blanche. Recherche live, tri au clic,
// pagination fluide et comparateur radar sont ensuite branchés en amélioration
// progressive par assets/js/pages/players.js sur les endpoints /api/players et
// /api/compare (mêmes listes blanches, côté client comme côté serveur).
final class PlayerController extends Controller
{
    // Listes blanches partagées avec l'API : aucune URL n'est forgée hors de ces valeurs.
    private const SORTABLE = ['last_name', 'shirt_number', 'position', 'nationality'];
    private const POSITIONS = ['GK', 'DF', 'MF', 'FW'];

    // Taille de page de l'effectif : 12 pour montrer une pagination réelle (24 joueurs
    // sur deux pages) tout en gardant des lignes de tableau confortables.
    private const PER_PAGE = 12;

    // Axes du radar de profil : même famille que le comparateur (ComparisonService),
    // chacun mappé vers sa clé de totaux et son libellé lisible.
    private const PROFILE_AXES = [
        'goals'    => 'Buts',
        'assists'  => 'Passes déc.',
        'minutes'  => 'Minutes',
        'shots'    => 'Tirs',
        'duelsWon' => 'Duels gagnés',
        'rating'   => 'Note',
    ];

    public function __construct(
        private ?PlayerRepository $players = null,
        private ?StatisticRepository $stats = null,
    ) {
        $this->players ??= new PlayerRepository();
        $this->stats ??= new StatisticRepository();
    }

    public function index(Request $r, array $params): void
    {
        $this->render('players', $this->buildViewData($_GET));
    }

    public function show(Request $r, array $params): void
    {
        $id = Validator::int($params['id'] ?? 0, 1, PHP_INT_MAX, 0);
        $data = $this->buildDetail($id);
        if ($data === null) {
            // Joueur introuvable : même rendu 404 que le front controller (index.php).
            Response::html(View::render('errors/404', [], 'main'), 404);
            return;
        }
        $this->render('player_detail', $data);
    }

    // Assemble la fiche joueur (identité, totaux saison, profil normalisé contre le
    // meilleur de l'effectif par axe, timeline de contribution), isolé du rendu pour
    // rester testable. Renvoie null si le joueur est introuvable (le contrôleur répond
    // alors 404). Traçabilité : les totaux saison sont vérifiés (FBref), l'attribution
    // par match de la timeline est estimée (la vue la signale comme telle).
    public function buildDetail(int $id): ?array
    {
        $player = $this->players->find($id);
        if ($player === null) {
            return null;
        }

        $totals = $this->stats->seasonTotalsByPlayer($id);
        $max = $this->stats->squadAxisMax();

        $axes = [];
        $values = [];
        foreach (self::PROFILE_AXES as $key => $label) {
            $axes[] = $label;
            $ceiling = (float) ($max[$key] ?? 0);
            $value = (float) ($totals[$key] ?? 0);
            $values[] = $ceiling > 0 ? round($value / $ceiling, 4) : 0.0;
        }

        return [
            'title'    => $player->fullName() . ' · PSG Analytics',
            'page'     => 'player-detail',
            'player'   => [
                'id'               => $player->id,
                'number'           => $player->shirtNumber,
                'name'             => $player->fullName(),
                'position'         => $player->position,
                'detailedPosition' => $player->detailedPosition,
                'foot'             => $player->foot,
                'nationality'      => $player->nationality,
                'birthDate'        => $player->birthDate,
                'heightCm'         => $player->heightCm,
                'isCaptain'        => $player->isCaptain,
            ],
            'totals'   => $totals,
            'profile'  => ['axes' => $axes, 'values' => $values],
            'timeline' => $this->stats->timeline($id),
        ];
    }

    // Assemble les données de la page à partir des paramètres de requête, validés par
    // liste blanche. Isolé de index() (aucun rendu ni effet de bord) pour rester
    // testable, à l'image des contrôleurs d'API du projet.
    public function buildViewData(array $query): array
    {
        $page = Validator::int($query['page'] ?? 1, 1, 9999, 1);
        $perPage = Validator::int($query['per_page'] ?? self::PER_PAGE, 1, 50, self::PER_PAGE);
        $sort = Validator::inList($query['sort'] ?? 'last_name', self::SORTABLE, 'last_name');
        // is_string() avant strtoupper() : un paramètre forgé en tableau (?order[]=x) ne
        // doit jamais atteindre une fonction de chaîne (sinon warning "Array to string").
        $orderRaw = $query['order'] ?? 'ASC';
        $order = Validator::inList(is_string($orderRaw) ? strtoupper($orderRaw) : '', ['ASC', 'DESC'], 'ASC');
        $position = isset($query['position'])
            ? (Validator::inList($query['position'], self::POSITIONS, '') ?: null)
            : null;

        $res = $this->players->paginate($page, $perPage, $sort, $order, $position);
        // Mêmes champs d'identité que /api/players : id, numéro, nom, poste, nationalité.
        $items = array_map(static fn (Player $p): array => [
            'id' => $p->id,
            'number' => $p->shirtNumber,
            'name' => $p->fullName(),
            'position' => $p->position,
            'nationality' => $p->nationality,
        ], $res['items']);

        $total = (int) $res['total'];
        $totalPages = (int) max(1, (int) ceil($total / $perPage));
        // Page demandée au-delà du dernier index (ex : filtre qui réduit le total) :
        // on la ramène dans les bornes pour un affichage cohérent des contrôles.
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return [
            'title'     => 'Joueurs · PSG Analytics',
            'page'      => 'players',
            'players'   => $items,
            'meta'      => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
            'sort'      => $sort,
            'order'     => $order,
            'position'  => $position,
            'positions' => self::POSITIONS,
        ];
    }
}
